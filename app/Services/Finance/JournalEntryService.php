<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalEntryLine;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalEntryService
{
    // ─── CRUD ─────────────────────────────────────────────────────────────────

    /**
     * Create a new journal entry with lines.
     */
    public function create(array $data): JournalEntry
    {
        return DB::transaction(function () use ($data): JournalEntry {
            $data['status'] = 'draft';
            $data['reference'] = $data['reference'] ?? $this->generateReference();
            $data['created_by'] = auth()->id();

            $entry = JournalEntry::create(Arr::except($data, ['lines']));

            foreach ($data['lines'] ?? [] as $index => $lineData) {
                $entry->lines()->create($this->lineAttributes($entry, $lineData, $index));
            }

            $this->calculateTotals($entry);

            return $entry->fresh(['lines']);
        });
    }

    /**
     * Update a journal entry and sync its lines (only allowed in draft status).
     */
    public function update(JournalEntry $entry, array $data): JournalEntry
    {
        if ($entry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft journal entries can be updated. Current status: {$entry->status}.",
            ]);
        }

        return DB::transaction(function () use ($entry, $data): JournalEntry {
            $entry->update(Arr::except($data, ['lines', 'status', 'company_id']) + ['updated_by' => auth()->id()]);

            if (array_key_exists('lines', $data)) {
                $existingIds = $entry->lines()->pluck('id')->toArray();
                $incomingIds = [];

                foreach ($data['lines'] as $index => $lineData) {
                    $attributes = $this->lineAttributes($entry, $lineData, $index);
                    $line = !empty($lineData['id']) ? $entry->lines()->find($lineData['id']) : null;

                    if ($line) {
                        $line->update($attributes);
                        $incomingIds[] = $line->id;
                    } else {
                        $incomingIds[] = $entry->lines()->create($attributes)->id;
                    }
                }

                $toDelete = array_diff($existingIds, $incomingIds);
                if (!empty($toDelete)) {
                    $entry->lines()->whereIn('id', $toDelete)->delete();
                }
            }

            $this->calculateTotals($entry);

            return $entry->fresh(['lines']);
        });
    }

    /**
     * Soft-delete a journal entry (only allowed in draft status).
     */
    public function delete(JournalEntry $entry): bool
    {
        if ($entry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft journal entries can be deleted. Current status: {$entry->status}.",
            ]);
        }

        return (bool) $entry->delete();
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    /**
     * Post a journal entry (transition from draft → posted).
     * Validates that total debits equal total credits.
     */
    public function post(JournalEntry $entry): JournalEntry
    {
        if ($entry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft journal entries can be posted. Current status: {$entry->status}.",
            ]);
        }

        $entry->loadMissing('lines');

        if ($entry->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Journal entry must have at least one line.',
            ]);
        }

        // Validate debit = credit
        $totalDebit = $entry->lines->sum('debit');
        $totalCredit = $entry->lines->sum('credit');

        if (abs((float) $totalDebit - (float) $totalCredit) > 0.01) {
            throw ValidationException::withMessages([
                'lines' => "Total debits ({$totalDebit}) must equal total credits ({$totalCredit}).",
            ]);
        }

        $entry->update([
            'status'    => 'posted',
            'posted_by' => auth()->id(),
            'posted_at' => now(),
        ]);

        return $entry->fresh(['lines']);
    }

    /**
     * Cancel a journal entry (only allowed in draft status).
     */
    public function cancel(JournalEntry $entry, ?string $reason = null): JournalEntry
    {
        if ($entry->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => "Only draft journal entries can be cancelled. Current status: {$entry->status}.",
            ]);
        }

        $entry->update([
            'status'              => 'cancelled',
            'cancelled_by'        => auth()->id(),
            'cancelled_at'        => now(),
            'cancellation_reason' => $reason,
        ]);

        return $entry->fresh(['lines']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function lineAttributes(JournalEntry $entry, array $lineData, int $index): array
    {
        return [
            'company_id'          => $entry->company_id,
            'chart_of_account_id' => $lineData['chart_of_account_id'],
            'description'         => $lineData['description'] ?? null,
            'debit'               => round((float) ($lineData['debit'] ?? 0), 2),
            'credit'              => round((float) ($lineData['credit'] ?? 0), 2),
            'sort_order'          => $lineData['sort_order'] ?? $index,
        ];
    }

    /**
     * Next EC-YYYY-NNNNN reference ("écriture comptable") for the current company.
     */
    private function generateReference(): string
    {
        $year = now()->format('Y');
        $last = JournalEntry::withTrashed()->where('reference', 'like', "EC-{$year}-%")->orderByDesc('reference')->value('reference');
        $next = $last ? (int) substr($last, -5) + 1 : 1;

        return sprintf('EC-%s-%05d', $year, $next);
    }

    // ─── Calculations ─────────────────────────────────────────────────────────

    /**
     * Recalculate and persist total_debit and total_credit.
     */
    public function calculateTotals(JournalEntry $entry): void
    {
        $entry->load('lines');

        $totalDebit = $entry->lines->sum('debit');
        $totalCredit = $entry->lines->sum('credit');

        $entry->update([
            'total_debit'  => round((float) $totalDebit, 2),
            'total_credit' => round((float) $totalCredit, 2),
        ]);
    }
}
