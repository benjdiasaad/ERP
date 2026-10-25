<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Models\CRM\Lead;
use App\Models\CRM\Opportunity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeadService
{
    public function __construct(
        private readonly OpportunityService $opportunityService,
    ) {}

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): Lead
    {
        return DB::transaction(function () use ($data): Lead {
            $data['created_by'] = auth()->id();
            $data['status'] = $data['status'] ?? 'new';
            $data['reference'] = $this->nextReference();

            return Lead::create($data);
        });
    }

    /**
     * Update a lead. Converted and lost leads are read-only.
     */
    public function update(Lead $lead, array $data): Lead
    {
        $this->ensureOpen($lead, 'updated');

        if (($data['status'] ?? null) === 'lost' && blank($data['lost_reason'] ?? $lead->lost_reason)) {
            throw ValidationException::withMessages([
                'lost_reason' => 'A reason is required when marking a lead as lost.',
            ]);
        }

        $lead->update($data);

        return $lead->fresh(['contact', 'assignee']);
    }

    /**
     * Soft-delete a lead. Converted leads are kept for traceability.
     */
    public function delete(Lead $lead): bool
    {
        if ($lead->status === 'converted') {
            throw ValidationException::withMessages([
                'status' => 'Converted leads cannot be deleted.',
            ]);
        }

        return (bool) $lead->delete();
    }

    // ─── Workflow ─────────────────────────────────────────────────────────────

    /**
     * Qualify a lead (new|contacted|unqualified → qualified).
     */
    public function qualify(Lead $lead, array $data = []): Lead
    {
        if (!in_array($lead->status, ['new', 'contacted', 'unqualified'], true)) {
            throw ValidationException::withMessages([
                'status' => "Only new, contacted or unqualified leads can be qualified. Current status: {$lead->status}.",
            ]);
        }

        $lead->update(array_merge(
            array_intersect_key($data, array_flip(['estimated_value', 'probability', 'expected_close_date'])),
            ['status' => 'qualified', 'qualified_at' => now()]
        ));

        return $lead->fresh(['contact', 'assignee']);
    }

    /**
     * Convert a qualified lead into an opportunity.
     *
     * @return array{lead: Lead, opportunity: Opportunity}
     */
    public function convert(Lead $lead, array $data = []): array
    {
        if ($lead->status !== 'qualified') {
            throw ValidationException::withMessages([
                'status' => "Only qualified leads can be converted. Current status: {$lead->status}.",
            ]);
        }

        return DB::transaction(function () use ($lead, $data): array {
            $lead->loadMissing('contact');

            $opportunity = $this->opportunityService->create(array_merge([
                'lead_id'             => $lead->id,
                'contact_id'          => $lead->contact_id,
                'customer_id'         => $lead->contact?->customer_id,
                'title'               => $lead->title,
                'description'         => $lead->description,
                'value'               => $lead->estimated_value ?? 0,
                'probability'         => $lead->probability,
                'expected_close_date' => $lead->expected_close_date?->toDateString(),
                'assigned_to'         => $lead->assigned_to,
                'tags'                => $lead->tags,
            ], array_filter($data, fn ($value) => $value !== null)));

            $lead->update([
                'status'                      => 'converted',
                'converted_at'                => now(),
                'converted_to_opportunity_id' => $opportunity->id,
            ]);

            return [
                'lead'        => $lead->fresh(['contact', 'assignee']),
                'opportunity' => $opportunity,
            ];
        });
    }

    /**
     * Assign a lead to a user (or unassign with null).
     */
    public function assign(Lead $lead, ?int $userId): Lead
    {
        $this->ensureOpen($lead, 'assigned');

        $lead->update(['assigned_to' => $userId]);

        return $lead->fresh(['contact', 'assignee']);
    }

    // ─── Reporting ────────────────────────────────────────────────────────────

    /**
     * Lead counts and estimated value per status.
     *
     * @return array{stages: array<string, array{count: int, estimated_value: float}>, total_count: int, total_estimated_value: float, conversion_rate: float}
     */
    public function pipelineStats(): array
    {
        $rows = Lead::query()
            ->selectRaw('status, COUNT(*) as aggregate_count, COALESCE(SUM(estimated_value), 0) as aggregate_value')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $stages = [];
        foreach (Lead::STATUSES as $status) {
            $stages[$status] = [
                'count'           => (int) ($rows[$status]->aggregate_count ?? 0),
                'estimated_value' => round((float) ($rows[$status]->aggregate_value ?? 0), 2),
            ];
        }

        $totalCount = array_sum(array_column($stages, 'count'));
        $closed = $stages['converted']['count'] + $stages['lost']['count'] + $stages['unqualified']['count'];

        return [
            'stages'                => $stages,
            'total_count'           => $totalCount,
            'total_estimated_value' => round(array_sum(array_column($stages, 'estimated_value')), 2),
            'conversion_rate'       => $closed > 0 ? round($stages['converted']['count'] / $closed * 100, 2) : 0.0,
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function ensureOpen(Lead $lead, string $action): void
    {
        if (!in_array($lead->status, Lead::OPEN_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => "A {$lead->status} lead cannot be {$action}.",
            ]);
        }
    }

    private function nextReference(): string
    {
        $prefix = 'LD-' . date('Y') . '-';

        $last = Lead::withTrashed()
            ->where('reference', 'like', $prefix . '%')
            ->orderByDesc('reference')
            ->value('reference');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
