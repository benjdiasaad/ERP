<?php

declare(strict_types=1);

namespace App\Services\Caution;

use App\Models\Caution\Caution;
use App\Models\Caution\CautionHistory;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CautionService
{
    /**
     * List / filter cautions with pagination.
     *
     * Supported filters: search (string), direction (given/received), status, partner_type, paginate (int, default 15).
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = Caution::query()->with(['cautionType', 'partner', 'related']);

        if (!empty($filters['search'])) {
            $term = mb_strtolower($filters['search']);
            $like = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($term, $like) {
                $q->where('notes', $like, "%{$term}%")
                  ->orWhereHas('partner', fn($subQ) => $subQ->where('name', $like, "%{$term}%"));
            });
        }

        if (!empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['partner_type'])) {
            $query->where('partner_type', $filters['partner_type']);
        }

        $perPage = $filters['paginate'] ?? 15;

        return $query->orderBy('issued_at', 'desc')->paginate($perPage);
    }

    /**
     * Create a new caution record.
     */
    public function create(array $data): Caution
    {
        return DB::transaction(function () use ($data): Caution {
            $caution = Caution::create([
                'company_id' => auth()->user()->current_company_id,
                'caution_type_id' => $data['caution_type_id'],
                'direction' => $data['direction'],
                'partner_type' => $data['partner_type'],
                'partner_id' => $data['partner_id'],
                'related_type' => $data['related_type'] ?? null,
                'related_id' => $data['related_id'] ?? null,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'MAD',
                'issue_date' => $data['issue_date'],
                'expiry_date' => $data['expiry_date'],
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account' => $data['bank_account'] ?? null,
                'bank_reference' => $data['bank_reference'] ?? null,
                'document_reference' => $data['document_reference'] ?? null,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logHistory($caution, 'created', null, 'draft', $data['amount']);

            return $caution->fresh(['cautionType', 'partner', 'related']);
        });
    }

    /**
     * Update an existing caution record.
     */
    public function update(Caution $caution, array $data): Caution
    {
        return DB::transaction(function () use ($caution, $data): Caution {
            $oldStatus = $caution->status;

            $updateData = [
                'caution_type_id' => $data['caution_type_id'] ?? $caution->caution_type_id,
                'amount' => $data['amount'] ?? $caution->amount,
                'issue_date' => $data['issue_date'] ?? $caution->issue_date,
                'expiry_date' => $data['expiry_date'] ?? $caution->expiry_date,
                'bank_name' => $data['bank_name'] ?? $caution->bank_name,
                'bank_account' => $data['bank_account'] ?? $caution->bank_account,
                'bank_reference' => $data['bank_reference'] ?? $caution->bank_reference,
                'document_reference' => $data['document_reference'] ?? $caution->document_reference,
                'notes' => $data['notes'] ?? $caution->notes,
            ];

            $caution->update($updateData);

            if ($oldStatus !== $caution->status) {
                $this->logHistory($caution, 'updated', $oldStatus, $caution->status, $caution->amount);
            }

            return $caution->fresh(['cautionType', 'partner', 'related']);
        });
    }

    /**
     * Soft-delete a caution record.
     */
    public function delete(Caution $caution): void
    {
        $caution->delete();
    }

    /**
     * Activate a caution (change status from draft to active).
     */
    public function activate(Caution $caution): Caution
    {
        return DB::transaction(function () use ($caution): Caution {
            if ($caution->status !== 'draft') {
                throw new \InvalidArgumentException('Only draft cautions can be activated.');
            }

            $oldStatus = $caution->status;
            $caution->update(['status' => 'active']);

            $this->logHistory($caution, 'activated', $oldStatus, 'active', $caution->amount);

            return $caution->fresh(['cautionType', 'partner', 'related']);
        });
    }

    /**
     * Record a partial return of caution amount.
     *
     * @param Caution $caution
     * @param float $amount Amount being returned
     * @param string|null $notes Optional notes about the return
     */
    public function partialReturn(Caution $caution, float $amount, ?string $notes = null): Caution
    {
        return DB::transaction(function () use ($caution, $amount, $notes): Caution {
            if ($caution->status !== 'active' && $caution->status !== 'partially_returned') {
                throw new \InvalidArgumentException('Only active or partially returned cautions can have partial returns.');
            }

            if ($amount <= 0) {
                throw new \InvalidArgumentException('Return amount must be greater than zero.');
            }

            $oldStatus = $caution->status;
            $newStatus = 'partially_returned';

            $caution->update([
                'status' => $newStatus,
                'notes' => ($caution->notes ? $caution->notes . "\n" : '') . ($notes ?? 'Partial return recorded'),
            ]);

            $this->logHistory($caution, 'partial_return', $oldStatus, $newStatus, $amount, $notes);

            return $caution->fresh(['cautionType', 'partner', 'related']);
        });
    }

    /**
     * Record a full return of caution amount.
     *
     * @param Caution $caution
     * @param string|null $notes Optional notes about the return
     */
    public function fullReturn(Caution $caution, ?string $notes = null): Caution
    {
        return DB::transaction(function () use ($caution, $notes): Caution {
            if (!in_array($caution->status, ['active', 'partially_returned'])) {
                throw new \InvalidArgumentException('Only active or partially returned cautions can be fully returned.');
            }

            $oldStatus = $caution->status;
            $newStatus = 'returned';

            $caution->update([
                'status' => $newStatus,
                'return_date' => now(),
                'notes' => ($caution->notes ? $caution->notes . "\n" : '') . ($notes ?? 'Full return recorded'),
            ]);

            $this->logHistory($caution, 'full_return', $oldStatus, $newStatus, $caution->amount, $notes);

            return $caution->fresh(['cautionType', 'partner', 'related']);
        });
    }

    /**
     * Extend the expiry date of a caution.
     *
     * @param Caution $caution
     * @param Carbon $newExpiryDate New expiry date
     * @param string|null $notes Optional notes about the extension
     */
    public function extend(Caution $caution, Carbon $newExpiryDate, ?string $notes = null): Caution
    {
        return DB::transaction(function () use ($caution, $newExpiryDate, $notes): Caution {
            if ($newExpiryDate->lessThanOrEqualTo($caution->expiry_date)) {
                throw new \InvalidArgumentException('New expiry date must be after the current expiry date.');
            }

            $oldExpiryDate = $caution->expiry_date;

            $caution->update([
                'expiry_date' => $newExpiryDate,
                'notes' => ($caution->notes ? $caution->notes . "\n" : '') . ($notes ?? "Extended from {$oldExpiryDate->toDateString()} to {$newExpiryDate->toDateString()}"),
            ]);

            $this->logHistory($caution, 'extended', $caution->status, $caution->status, $caution->amount, $notes);

            return $caution->fresh(['cautionType', 'partner', 'related']);
        });
    }

    /**
     * Forfeit a caution (mark as forfeited).
     *
     * @param Caution $caution
     * @param string|null $notes Reason for forfeiture
     */
    public function forfeit(Caution $caution, ?string $notes = null): Caution
    {
        return DB::transaction(function () use ($caution, $notes): Caution {
            if (!in_array($caution->status, ['active', 'partially_returned'])) {
                throw new \InvalidArgumentException('Only active or partially returned cautions can be forfeited.');
            }

            $oldStatus = $caution->status;
            $newStatus = 'forfeited';

            $caution->update([
                'status' => $newStatus,
                'notes' => ($caution->notes ? $caution->notes . "\n" : '') . ($notes ?? 'Caution forfeited'),
            ]);

            $this->logHistory($caution, 'forfeited', $oldStatus, $newStatus, $caution->amount, $notes);

            return $caution->fresh(['cautionType', 'partner', 'related']);
        });
    }

    /**
     * Cancel a caution (only draft cautions can be cancelled).
     *
     * @param Caution $caution
     * @param string|null $notes Reason for cancellation
     */
    public function cancel(Caution $caution, ?string $notes = null): Caution
    {
        return DB::transaction(function () use ($caution, $notes): Caution {
            if ($caution->status !== 'draft') {
                throw new \InvalidArgumentException('Only draft cautions can be cancelled.');
            }

            $oldStatus = $caution->status;
            $newStatus = 'cancelled';

            $caution->update([
                'status' => $newStatus,
                'notes' => ($caution->notes ? $caution->notes . "\n" : '') . ($notes ?? 'Caution cancelled'),
            ]);

            $this->logHistory($caution, 'cancelled', $oldStatus, $newStatus, $caution->amount, $notes);

            return $caution->fresh(['cautionType', 'partner', 'related']);
        });
    }

    /**
     * Get cautions expiring within a specified number of days.
     *
     * @param int $daysAhead Number of days ahead to check (default 30)
     */
    public function getExpiring(int $daysAhead = 30): Collection
    {
        $expiryDate = now()->addDays($daysAhead);

        return Caution::query()
            ->where('status', 'active')
            ->whereBetween('expiry_date', [now(), $expiryDate])
            ->orderBy('expiry_date', 'asc')
            ->get();
    }

    /**
     * Get cautions by partner (customer/supplier/other).
     *
     * @param string $partnerType Type of partner (e.g., 'App\Models\Sales\Customer')
     * @param int $partnerId ID of the partner
     */
    public function getByPartner(string $partnerType, int $partnerId): Collection
    {
        return Caution::query()
            ->where('partner_type', $partnerType)
            ->where('partner_id', $partnerId)
            ->orderBy('issued_at', 'desc')
            ->get();
    }

    /**
     * Get dashboard statistics for cautions.
     *
     * Returns: total_given, total_received, active_count, expiring_count, forfeited_total
     */
    public function getDashboardStats(): array
    {
        $totalGiven = Caution::query()
            ->where('direction', 'given')
            ->sum('amount');

        $totalReceived = Caution::query()
            ->where('direction', 'received')
            ->sum('amount');

        $activeCount = Caution::query()
            ->where('status', 'active')
            ->count();

        $expiringCount = Caution::query()
            ->where('status', 'active')
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->count();

        $forfeitedTotal = Caution::query()
            ->where('status', 'forfeited')
            ->sum('amount');

        return [
            'total_given' => (float) $totalGiven,
            'total_received' => (float) $totalReceived,
            'active_count' => $activeCount,
            'expiring_count' => $expiringCount,
            'forfeited_total' => (float) $forfeitedTotal,
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Log a history entry for caution actions.
     */
    private function logHistory(
        Caution $caution,
        string $action,
        ?string $previousStatus,
        string $newStatus,
        float $amount,
        ?string $notes = null
    ): void {
        CautionHistory::create([
            'company_id' => auth()->user()->current_company_id,
            'caution_id' => $caution->id,
            'action' => $action,
            'amount' => $amount,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'created_by' => auth()->id(),
        ]);
    }
}
