<?php

declare(strict_types=1);

namespace App\Policies\Caution;

use App\Models\Auth\User;
use App\Models\Caution\Caution;

class CautionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('cautions.view_any');
    }

    public function view(User $user, Caution $caution): bool
    {
        return $user->hasPermission('cautions.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('cautions.create');
    }

    public function update(User $user, Caution $caution): bool
    {
        return $user->hasPermission('cautions.update');
    }

    public function delete(User $user, Caution $caution): bool
    {
        return $user->hasPermission('cautions.delete');
    }

    public function restore(User $user, Caution $caution): bool
    {
        return $user->hasPermission('cautions.restore');
    }

    public function forceDelete(User $user, Caution $caution): bool
    {
        return $user->hasPermission('cautions.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('cautions.export');
    }
}
