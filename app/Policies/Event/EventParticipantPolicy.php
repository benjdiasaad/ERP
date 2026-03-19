<?php

declare(strict_types=1);

namespace App\Policies\Event;

use App\Models\Auth\User;
use App\Models\Event\EventParticipant;

class EventParticipantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('event_participants.view_any');
    }

    public function view(User $user, EventParticipant $eventParticipant): bool
    {
        return $user->hasPermission('event_participants.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('event_participants.create');
    }

    public function update(User $user, EventParticipant $eventParticipant): bool
    {
        return $user->hasPermission('event_participants.update');
    }

    public function delete(User $user, EventParticipant $eventParticipant): bool
    {
        return $user->hasPermission('event_participants.delete');
    }

    public function restore(User $user, EventParticipant $eventParticipant): bool
    {
        return $user->hasPermission('event_participants.restore');
    }

    public function forceDelete(User $user, EventParticipant $eventParticipant): bool
    {
        return $user->hasPermission('event_participants.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('event_participants.export');
    }
}
