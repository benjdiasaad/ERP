<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\Contract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ContractService
{
    /**
     * Create a new contract.
     */
    public function create(array $data): Contract
    {
        return DB::transaction(function () use ($data): Contract {
            $data['created_by'] = auth()->id();

            if (empty($data['reference'])) {
                $data['reference'] = $this->generateReference();
            }

            return Contract::create($data);
        });
    }

    /**
     * Update an existing contract.
     */
    public function update(Contract $contract, array $data): Contract
    {
        $contract->update($data);

        return $contract->fresh(['personnel']);
    }

    /**
     * Terminate a contract by setting its end date, termination timestamp and reason.
     */
    public function terminate(Contract $contract, string $terminationDate, string $reason): Contract
    {
        $contract->update([
            'end_date'           => $terminationDate,
            'status'             => 'terminated',
            'terminated_at'      => now(),
            'termination_reason' => $reason,
        ]);

        return $contract->fresh();
    }

    /**
     * Get the currently active contract for a personnel member.
     */
    public function getActive(int $personnelId): ?Contract
    {
        return Contract::where('personnel_id', $personnelId)
            ->where('status', 'active')
            ->whereNull('terminated_at')
            ->latest('start_date')
            ->first();
    }

    /**
     * Get the full contract history for a personnel member.
     */
    public function getHistory(int $personnelId): Collection
    {
        return Contract::where('personnel_id', $personnelId)
            ->orderByDesc('start_date')
            ->get();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generateReference(): string
    {
        $year  = now()->year;
        $count = Contract::withoutGlobalScopes()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('CTR-%d-%05d', $year, $count);
    }
}
