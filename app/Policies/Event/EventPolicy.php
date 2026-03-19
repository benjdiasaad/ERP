<?php

declare(strict_types=1);

namespace App\Policies\Event;

use App\Models\Auth\User;
use App\Models\Event\Event;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('events.view_any');
    }

    public function view(User $user, Event $event): bool
    {
        return $user->hasPermission('events.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('events.create');
    }

    public function update(User $user, Event $event): bool
    {
        return $user->hasPermission('events.update');
    }

    public function delete(User $user, Event $event): bool
    {
        return $user->hasPermission('events.delete');
    }

    public function restore(User $user, Event $event): bool
    {
        return $user->hasPermission('events.restore');
    }

    public function forceDelete(User $user, Event $event): bool
    {
        return $user->hasPermission('events.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('events.export');
    }
}
