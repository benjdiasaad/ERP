<?php

declare(strict_types=1);

namespace App\Services\CRM;

use App\Models\CRM\Activity;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ActivityService
{
    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function create(array $data): Activity
    {
        $data = $this->resolveRelated($data);
        $data['created_by'] = auth()->id();
        $data['status'] = $data['status'] ?? 'pending';

        if ($data['status'] === 'completed') {
            $data['completed_at'] = $data['completed_at'] ?? now();
        }

        return Activity::create($data);
    }

    public function update(Activity $activity, array $data): Activity
    {
        if (in_array($activity->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => "A {$activity->status} activity cannot be updated.",
            ]);
        }

        $data = $this->resolveRelated($data);

        if (($data['status'] ?? null) === 'completed') {
            $data['completed_at'] = now();
        }

        $activity->update($data);

        return $activity->fresh(['related', 'assignee']);
    }

    public function delete(Activity $activity): bool
    {
        return (bool) $activity->delete();
    }

    // ─── Workflow ─────────────────────────────────────────────────────────────

    public function complete(Activity $activity, ?string $outcome = null): Activity
    {
        if (in_array($activity->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => "A {$activity->status} activity cannot be completed.",
            ]);
        }

        $activity->update([
            'status'       => 'completed',
            'completed_at' => now(),
            'outcome'      => $outcome ?? $activity->outcome,
        ]);

        return $activity->fresh(['related', 'assignee']);
    }

    // ─── Queries ──────────────────────────────────────────────────────────────

    /**
     * Pending or in-progress activities whose due date has passed.
     */
    public function getOverdue(?int $assignedTo = null): Collection
    {
        return Activity::whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->when($assignedTo, fn ($query) => $query->where('assigned_to', $assignedTo))
            ->with(['related', 'assignee'])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Pending or in-progress activities due within the next $days days.
     */
    public function getUpcoming(int $days = 7, ?int $assignedTo = null): Collection
    {
        return Activity::whereIn('status', ['pending', 'in_progress'])
            ->whereBetween('due_date', [now(), now()->addDays($days)])
            ->when($assignedTo, fn ($query) => $query->where('assigned_to', $assignedTo))
            ->with(['related', 'assignee'])
            ->orderBy('due_date')
            ->get();
    }

    public function getByRelated(string $alias, int $relatedId): Collection
    {
        return Activity::where('related_type', Activity::RELATED_TYPES[$alias] ?? $alias)
            ->where('related_id', $relatedId)
            ->with(['assignee'])
            ->orderByDesc('due_date')
            ->get();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Translate the API alias (contact, lead, …) into the model class and make
     * sure the related record exists in the current company.
     */
    private function resolveRelated(array $data): array
    {
        if (!array_key_exists('related_type', $data)) {
            return $data;
        }

        if ($data['related_type'] === null) {
            $data['related_id'] = null;

            return $data;
        }

        $class = Activity::RELATED_TYPES[$data['related_type']] ?? null;

        if ($class === null || empty($data['related_id']) || !$class::whereKey($data['related_id'])->exists()) {
            throw ValidationException::withMessages([
                'related_id' => 'The related record does not exist.',
            ]);
        }

        $data['related_type'] = $class;

        return $data;
    }
}
