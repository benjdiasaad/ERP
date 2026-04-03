<?php

declare(strict_types=1);

namespace App\Services\Event;

use App\Models\Event\Event;
use App\Models\Event\EventParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventParticipantService
{
    // ─── Participant Management ───────────────────────────────────────────────

    /**
     * Invite a participant to an event.
     * Can be internal (user_id or personnel_id) or external (name/email).
     */
    public function invite(Event $event, array $data): EventParticipant
    {
        return DB::transaction(function () use ($event, $data): EventParticipant {
            // Validate that at least one identifier is provided
            if (empty($data['user_id']) && empty($data['personnel_id']) && empty($data['email'])) {
                throw ValidationException::withMessages([
                    'participant' => 'Either user_id, personnel_id, or email must be provided.',
                ]);
            }

            // Check for duplicate participant
            $query = EventParticipant::where('event_id', $event->id);

            if (!empty($data['user_id'])) {
                $query->where('user_id', $data['user_id']);
            } elseif (!empty($data['personnel_id'])) {
                $query->where('personnel_id', $data['personnel_id']);
            } else {
                $query->where('email', $data['email']);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'participant' => 'Participant is already invited to this event.',
                ]);
            }

            $data['company_id'] = $event->company_id;
            $data['event_id'] = $event->id;
            $data['rsvp_status'] = $data['rsvp_status'] ?? 'pending';
            $data['role'] = $data['role'] ?? 'attendee';

            return EventParticipant::create($data);
        });
    }

    /**
     * Confirm participation (RSVP yes).
     */
    public function confirm(EventParticipant $participant): EventParticipant
    {
        if ($participant->rsvp_status === 'confirmed') {
            throw ValidationException::withMessages([
                'rsvp_status' => 'Participant has already confirmed.',
            ]);
        }

        $participant->update([
            'rsvp_status' => 'confirmed',
        ]);

        return $participant->fresh();
    }

    /**
     * Decline participation (RSVP no).
     */
    public function decline(EventParticipant $participant): EventParticipant
    {
        if ($participant->rsvp_status === 'declined') {
            throw ValidationException::withMessages([
                'rsvp_status' => 'Participant has already declined.',
            ]);
        }

        $participant->update([
            'rsvp_status' => 'declined',
        ]);

        return $participant->fresh();
    }

    /**
     * Bulk invite multiple participants to an event.
     * Accepts array of participant data.
     */
    public function bulkInvite(Event $event, array $participants): array
    {
        return DB::transaction(function () use ($event, $participants): array {
            $created = [];
            $failed = [];

            foreach ($participants as $index => $participantData) {
                try {
                    $created[] = $this->invite($event, $participantData);
                } catch (\Exception $e) {
                    $failed[] = [
                        'index' => $index,
                        'data' => $participantData,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return [
                'created' => $created,
                'failed' => $failed,
            ];
        });
    }

    /**
     * Remove a participant from an event.
     */
    public function remove(EventParticipant $participant): bool
    {
        return (bool) $participant->delete();
    }
}
