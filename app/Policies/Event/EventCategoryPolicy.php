<?php

declare(strict_types=1);

namespace App\Policies\Event;

use App\Models\Auth\User;
use App\Models\Event\EventCategory;

class EventCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('event_categories.view_any');
    }

    public function view(User $user, EventCategory $eventCategory): bool
    {
        return $user->hasPermission('event_categories.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('event_categories.create');
    }

    public function update(User $user, EventCategory $eventCategory): bool
    {
        return $user->hasPermission('event_categories.update');
    }

    public function delete(User $user, EventCategory $eventCategory): bool
    {
        return $user->hasPermission('event_categories.delete');
    }

    public function restore(User $user, EventCategory $eventCategory): bool
    {
        return $user->hasPermission('event_categories.restore');
    }

    public function forceDelete(User $user, EventCategory $eventCategory): bool
    {
        return $user->hasPermission('event_categories.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('event_categories.export');
    }
}
