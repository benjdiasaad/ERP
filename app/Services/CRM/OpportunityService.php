<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Models\CRM\Opportunity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpportunityService
{
    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): Opportunity
    {
        return DB::transaction(function () use ($data): Opportunity {
            $data['created_by'] = auth()->id();
            $data['stage'] = $data['stage'] ?? 'prospecting';

            if (!in_array($data['stage'], Opportunity::OPEN_STAGES, true)) {
                throw ValidationException::withMessages([
                    'stage' => 'New opportunities must start in an open stage.',
                ]);
            }

            $data['probability'] = $data['probability'] ?? Opportunity::STAGE_PROBABILITIES[$data['stage']];
            $data['value'] = $data['value'] ?? 0;
            $data['reference'] = $this->nextReference();

            return Opportunity::create($data);
        });
    }

    /**
     * Update an open opportunity. Stage changes go through advance/win/lose.
     */
    public function update(Opportunity $opportunity, array $data): Opportunity
    {
        $this->ensureOpen($opportunity, 'updated');

        unset($data['stage']);
        $opportunity->update($data);

        return $opportunity->fresh(['contact', 'customer', 'assignee']);
    }

    public function delete(Opportunity $opportunity): bool
    {
        if ($opportunity->stage === 'closed_won') {
            throw ValidationException::withMessages([
                'stage' => 'Won opportunities cannot be deleted.',
            ]);
        }

        return (bool) $opportunity->delete();
    }

    // ─── Stage transitions ────────────────────────────────────────────────────

    /**
     * Move an opportunity forward in the pipeline.
     *
     * Without $stage, it moves to the next open stage. With $stage, it jumps
     * to that later open stage. Closing is done via win()/lose().
     */
    public function advance(Opportunity $opportunity, ?string $stage = null, ?int $probability = null): Opportunity
    {
        $this->ensureOpen($opportunity, 'advanced');

        $currentIndex = array_search($opportunity->stage, Opportunity::OPEN_STAGES, true);

        if ($stage === null) {
            $stage = Opportunity::OPEN_STAGES[$currentIndex + 1] ?? null;

            if ($stage === null) {
                throw ValidationException::withMessages([
                    'stage' => 'Opportunity is in the last open stage; mark it as won or lost.',
                ]);
            }
        }

        $targetIndex = array_search($stage, Opportunity::OPEN_STAGES, true);

        if ($targetIndex === false || $targetIndex <= $currentIndex) {
            throw ValidationException::withMessages([
                'stage' => "Cannot advance from {$opportunity->stage} to {$stage}.",
            ]);
        }

        $opportunity->update([
            'stage'       => $stage,
            'probability' => $probability ?? max($opportunity->probability, Opportunity::STAGE_PROBABILITIES[$stage]),
        ]);

        return $opportunity->fresh(['contact', 'customer', 'assignee']);
    }

    public function win(Opportunity $opportunity, array $data = []): Opportunity
    {
        $this->ensureOpen($opportunity, 'won');

        $opportunity->update([
            'stage'             => 'closed_won',
            'probability'       => 100,
            'value'             => $data['value'] ?? $opportunity->value,
            'actual_close_date' => $data['actual_close_date'] ?? now()->toDateString(),
            'won_quote_id'      => $data['won_quote_id'] ?? $opportunity->won_quote_id,
        ]);

        return $opportunity->fresh(['contact', 'customer', 'assignee']);
    }

    public function lose(Opportunity $opportunity, string $reason, ?string $closeDate = null): Opportunity
    {
        $this->ensureOpen($opportunity, 'lost');

        $opportunity->update([
            'stage'             => 'closed_lost',
            'probability'       => 0,
            'lost_reason'       => $reason,
            'actual_close_date' => $closeDate ?? now()->toDateString(),
        ]);

        return $opportunity->fresh(['contact', 'customer', 'assignee']);
    }

    // ─── Reporting ────────────────────────────────────────────────────────────

    /**
     * Count, total value and weighted value per stage.
     */
    public function pipeline(): array
    {
        $rows = Opportunity::query()
            ->selectRaw('stage, COUNT(*) as aggregate_count, COALESCE(SUM(value), 0) as aggregate_value, COALESCE(SUM(weighted_value), 0) as aggregate_weighted')
            ->groupBy('stage')
            ->get()
            ->keyBy('stage');

        $stages = [];
        foreach (array_keys(Opportunity::STAGE_PROBABILITIES) as $stage) {
            $stages[$stage] = [
                'count'          => (int) ($rows[$stage]->aggregate_count ?? 0),
                'value'          => round((float) ($rows[$stage]->aggregate_value ?? 0), 2),
                'weighted_value' => round((float) ($rows[$stage]->aggregate_weighted ?? 0), 2),
            ];
        }

        $open = array_intersect_key($stages, array_flip(Opportunity::OPEN_STAGES));
        $won = $stages['closed_won']['count'];
        $closed = $won + $stages['closed_lost']['count'];

        return [
            'stages'              => $stages,
            'open_count'          => array_sum(array_column($open, 'count')),
            'open_value'          => round(array_sum(array_column($open, 'value')), 2),
            'open_weighted_value' => round(array_sum(array_column($open, 'weighted_value')), 2),
            'win_rate'            => $closed > 0 ? round($won / $closed * 100, 2) : 0.0,
        ];
    }

    /**
     * Monthly forecast for the next $months months (current month included).
     *
     * Open opportunities are bucketed by expected close date; won ones by
     * actual close date. Overdue open opportunities (expected date in the
     * past) are counted in the current month.
     */
    public function forecast(int $months = 3, ?Carbon $from = null): array
    {
        $months = max(1, min(24, $months));
        $start = ($from ?? now())->copy()->startOfMonth();
        $end = $start->copy()->addMonths($months - 1)->endOfMonth();

        $periods = [];
        for ($i = 0; $i < $months; $i++) {
            $periods[$start->copy()->addMonths($i)->format('Y-m')] = [
                'period'         => $start->copy()->addMonths($i)->format('Y-m'),
                'open_count'     => 0,
                'pipeline_value' => 0.0,
                'weighted_value' => 0.0,
                'won_count'      => 0,
                'won_value'      => 0.0,
            ];
        }

        $open = Opportunity::whereIn('stage', Opportunity::OPEN_STAGES)
            ->whereNotNull('expected_close_date')
            ->whereDate('expected_close_date', '<=', $end->toDateString())
            ->get(['expected_close_date', 'value', 'weighted_value']);

        $currentKey = $start->format('Y-m');
        foreach ($open as $opportunity) {
            $key = $opportunity->expected_close_date->lt($start) ? $currentKey : $opportunity->expected_close_date->format('Y-m');
            $periods[$key]['open_count']++;
            $periods[$key]['pipeline_value'] += (float) $opportunity->value;
            $periods[$key]['weighted_value'] += (float) $opportunity->weighted_value;
        }

        $won = Opportunity::where('stage', 'closed_won')
            ->whereDate('actual_close_date', '>=', $start->toDateString())
            ->whereDate('actual_close_date', '<=', $end->toDateString())
            ->get(['actual_close_date', 'value']);

        foreach ($won as $opportunity) {
            $key = $opportunity->actual_close_date->format('Y-m');
            $periods[$key]['won_count']++;
            $periods[$key]['won_value'] += (float) $opportunity->value;
        }

        $periods = array_map(function (array $period): array {
            foreach (['pipeline_value', 'weighted_value', 'won_value'] as $field) {
                $period[$field] = round($period[$field], 2);
            }

            return $period;
        }, array_values($periods));

        return [
            'from'                 => $start->toDateString(),
            'to'                   => $end->toDateString(),
            'periods'              => $periods,
            'total_weighted_value' => round(array_sum(array_column($periods, 'weighted_value')), 2),
            'total_won_value'      => round(array_sum(array_column($periods, 'won_value')), 2),
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function ensureOpen(Opportunity $opportunity, string $action): void
    {
        if (!$opportunity->isOpen()) {
            throw ValidationException::withMessages([
                'stage' => "A closed opportunity ({$opportunity->stage}) cannot be {$action}.",
            ]);
        }
    }

    private function nextReference(): string
    {
        $prefix = 'OPP-' . date('Y') . '-';

        $last = Opportunity::withTrashed()
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('reference')
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
