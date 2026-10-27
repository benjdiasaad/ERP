<?php

declare(strict_types=1);

namespace App\Services\Personnel;

use App\Models\Personnel\Leave;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    /**
     * Create a new leave request (status defaults to pending).
     */
    public function create(array $data): Leave
    {
        return DB::transaction(function () use ($data): Leave {
            $data['status'] = 'pending';
            // The API calls it `type`; the column is `leave_type`.
            $data['leave_type'] = $data['leave_type'] ?? $data['type'] ?? null;
            unset($data['type']);
            $data['total_days'] = $data['total_days'] ?? $this->countDays($data['start_date'], $data['end_date']);

            return Leave::create($data);
        });
    }

    /**
     * Update an existing leave request.
     */
    public function update(Leave $leave, array $data): Leave
    {
        if (array_key_exists('type', $data)) {
            $data['leave_type'] = $data['type'];
            unset($data['type']);
        }
        if ((isset($data['start_date']) || isset($data['end_date'])) && !isset($data['total_days'])) {
            $data['total_days'] = $this->countDays($data['start_date'] ?? $leave->start_date, $data['end_date'] ?? $leave->end_date);
        }

        $leave->update($data);

        return $leave->fresh(['personnel', 'approvedBy']);
    }

    /**
     * Approve a leave request.
     */
    public function approve(Leave $leave, int $approverId): Leave
    {
        $this->ensurePending($leave);

        $leave->update([
            'status'      => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        return $leave->fresh(['approvedBy']);
    }

    /**
     * Reject a leave request with a reason.
     */
    public function reject(Leave $leave, int $approverId, string $reason): Leave
    {
        // An approved leave can still be revoked.
        if (!in_array($leave->status, ['pending', 'approved'], true)) {
            throw ValidationException::withMessages([
                'status' => "Only pending or approved leave requests can be rejected. Current status: {$leave->status}.",
            ]);
        }

        $leave->update([
            'status'           => 'rejected',
            'approved_by'      => $approverId,
            'approved_at'      => now(),
            'rejection_reason' => $reason,
        ]);

        return $leave->fresh(['approvedBy']);
    }

    /**
     * Cancel a leave request (only allowed when still pending).
     *
     * @throws ValidationException if the leave is not in pending status.
     */
    public function cancel(Leave $leave): Leave
    {
        if ($leave->status !== 'pending') {
            throw ValidationException::withMessages([
                'leave' => 'Only pending leave requests can be cancelled.',
            ]);
        }

        $leave->update(['status' => 'cancelled']);

        return $leave->fresh();
    }

    /**
     * Calculate the remaining leave balance for a personnel member.
     *
     * Balance = allocated days − used days (approved leaves in the given year).
     */
    public function getBalance(int $personnelId, string $type, int $year): float
    {
        $allocated = $this->getAllocatedDays($type);

        $used = Leave::where('personnel_id', $personnelId)
            ->where('leave_type', $type)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->sum('total_days');

        return max(0.0, $allocated - (float) $used);
    }

    /**
     * Get all pending leave requests for the current company.
     */
    public function getPendingApprovals(): Collection
    {
        return Leave::where('status', 'pending')
            ->with(['personnel'])
            ->orderBy('start_date')
            ->get();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Return the default annual allocation (in days) for a given leave type.
     */
    private function getAllocatedDays(string $type): float
    {
        return match ($type) {
            'annual'       => 22.0,
            'sick'         => 15.0,
            'maternity'    => 98.0,
            'paternity'    => 3.0,
            'compensatory' => 0.0,  // accrued dynamically
            default        => 0.0,
        };
    }

    /**
     * Calendar days covered by the leave, both ends included.
     */
    private function countDays(mixed $start, mixed $end): float
    {
        return (float) (\Illuminate\Support\Carbon::parse($start)->diffInDays(\Illuminate\Support\Carbon::parse($end)) + 1);
    }

    private function ensurePending(Leave $leave): void
    {
        if ($leave->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => "Only pending leave requests can be approved. Current status: {$leave->status}.",
            ]);
        }
    }
}
