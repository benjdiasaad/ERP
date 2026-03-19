<?php

declare(strict_types=1);

namespace App\Services\Event;

use App\Models\Event\Event;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventService
{
    // ─── CRUD ─────────────────────────────────────────────────────────────────

    /**
     * Create a new event.
     */
    public function create(array $data): Event
    {
        return DB::transaction(function () use ($data): Event {
            $data['created_by'] = auth()->id();
            $data['status'] = $data['status'] ?? 'planned';

            return Event::create($data);
        });
    }

    /**
     * Update an event (only allowed in draft/planned status).
     */
    public function update(Event $event, array $data): Event
    {
        if (!in_array($event->status, ['planned', 'draft'], true)) {
            throw ValidationException::withMessages([
                'status' => "Only planned or draft events can be updated. Current status: {$event->status}.",
            ]);
        }

        return DB::transaction(function () use ($event, $data): Event {
            $event->update($data);

            return $event->fresh(['category', 'participants']);
        });
    }

    /**
     * Soft-delete an event (only allowed in draft/planned status).
     */
    public function delete(Event $event): bool
    {
        if (!in_array($event->status, ['planned', 'draft'], true)) {
            throw ValidationException::withMessages([
                'status' => "Only planned or draft events can be deleted. Current status: {$event->status}.",
            ]);
        }

        return (bool) $event->delete();
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    /**
     * Confirm an event (transition from planned → confirmed).
     */
    public function confirm(Event $event): Event
    {
        if ($event->status !== 'planned') {
            throw ValidationException::withMessages([
                'status' => "Only planned events can be confirmed. Current status: {$event->status}.",
            ]);
        }

        $event->update([
            'status'       => 'confirmed',
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
        ]);

        return $event->fresh(['category', 'participants']);
    }

    /**
     * Cancel an event (not allowed if already completed).
     */
    public function cancel(Event $event, ?string $reason = null): Event
    {
        if ($event->status === 'completed') {
            throw ValidationException::withMessages([
                'status' => "Completed events cannot be cancelled.",
            ]);
        }

        if ($event->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'Event is already cancelled.',
            ]);
        }

        $event->update([
            'status'              => 'cancelled',
            'cancelled_by'        => auth()->id(),
            'cancelled_at'        => now(),
            'cancellation_reason' => $reason,
        ]);

        return $event->fresh(['category', 'participants']);
    }

    /**
     * Mark an event as completed.
     */
    public function complete(Event $event): Event
    {
        if ($event->status === 'completed') {
            throw ValidationException::withMessages([
                'status' => 'Event is already completed.',
            ]);
        }

        if ($event->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'Cancelled events cannot be completed.',
            ]);
        }

        $event->update([
            'status'       => 'completed',
            'completed_by' => auth()->id(),
            'completed_at' => now(),
        ]);

        return $event->fresh(['category', 'participants']);
    }

    /**
     * Postpone an event to a new date.
     */
    public function postpone(Event $event, array $data): Event
    {
        if ($event->status === 'completed') {
            throw ValidationException::withMessages([
                'status' => 'Completed events cannot be postponed.',
            ]);
        }

        if ($event->status === 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'Cancelled events cannot be postponed.',
            ]);
        }

        if (empty($data['start_date']) || empty($data['end_date'])) {
            throw ValidationException::withMessages([
                'dates' => 'Both start_date and end_date are required for postponement.',
            ]);
        }

        $startDate = Carbon::parse($data['start_date']);
        $endDate = Carbon::parse($data['end_date']);

        if ($endDate->isBefore($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'End date must be after start date.',
            ]);
        }

        $event->update([
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'postponed_by'    => auth()->id(),
            'postponed_at'    => now(),
            'postponement_reason' => $data['reason'] ?? null,
        ]);

        return $event->fresh(['category', 'participants']);
    }

    // ─── Queries ──────────────────────────────────────────────────────────────

    /**
     * Get upcoming events (start_date >= today, not cancelled/completed).
     * Ordered by start_date ascending.
     */
    public function getUpcoming(?int $limit = null): Collection
    {
        $query = Event::where('start_date', '>=', now()->toDateString())
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->orderBy('start_date', 'asc')
            ->with(['category', 'participants']);

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Get events within a date range.
     * Optionally filter by status.
     */
    public function getByDateRange(
        Carbon $startDate,
        Carbon $endDate,
        ?string $status = null
    ): Collection {
        $query = Event::whereBetween('start_date', [
            $startDate->toDateString(),
            $endDate->toDateString(),
        ])->with(['category', 'participants']);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('start_date', 'asc')->get();
    }

    /**
     * Get events by category.
     */
    public function getByCategory(int $categoryId): Collection
    {
        return Event::where('event_category_id', $categoryId)
            ->with(['category', 'participants'])
            ->orderBy('start_date', 'asc')
            ->get();
    }

    /**
     * Get events by status.
     */
    public function getByStatus(string $status): Collection
    {
        return Event::where('status', $status)
            ->with(['category', 'participants'])
            ->orderBy('start_date', 'asc')
            ->get();
    }

    /**
     * Get mandatory events.
     */
    public function getMandatory(): Collection
    {
        return Event::where('is_mandatory', true)
            ->where('start_date', '>=', now()->toDateString())
            ->with(['category', 'participants'])
            ->orderBy('start_date', 'asc')
            ->get();
    }

    /**
     * Get events expiring soon (within N days).
     */
    public function getExpiringSoon(int $daysAhead = 7): Collection
    {
        $futureDate = now()->addDays($daysAhead)->toDateString();

        return Event::whereBetween('start_date', [
            now()->toDateString(),
            $futureDate,
        ])->whereNotIn('status', ['cancelled', 'completed'])
            ->with(['category', 'participants'])
            ->orderBy('start_date', 'asc')
            ->get();
    }
}
