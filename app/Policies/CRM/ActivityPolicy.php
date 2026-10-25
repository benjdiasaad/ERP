<?php

declare(strict_types=1);

namespace App\Policies\CRM;

use App\Models\Auth\User;
use App\Models\CRM\Activity;

class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('activities.view_any');
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->hasPermission('activities.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('activities.create');
    }

    public function update(User $user, Activity $activity): bool
    {
        return $user->hasPermission('activities.update');
    }

    public function delete(User $user, Activity $activity): bool
    {
        return $user->hasPermission('activities.delete');
    }

    public function restore(User $user, Activity $activity): bool
    {
        return $user->hasPermission('activities.restore');
    }

    public function forceDelete(User $user, Activity $activity): bool
    {
        return $user->hasPermission('activities.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('activities.export');
    }

    public function complete(User $user, Activity $activity): bool
    {
        return $user->hasPermission('activities.complete');
    }
}
