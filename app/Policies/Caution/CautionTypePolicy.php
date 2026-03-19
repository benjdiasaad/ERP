<?php

declare(strict_types=1);

namespace App\Policies\Caution;

use App\Models\Auth\User;
use App\Models\Caution\CautionType;

class CautionTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('caution_types.view_any');
    }

    public function view(User $user, CautionType $cautionType): bool
    {
        return $user->hasPermission('caution_types.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('caution_types.create');
    }

    public function update(User $user, CautionType $cautionType): bool
    {
        return $user->hasPermission('caution_types.update');
    }

    public function delete(User $user, CautionType $cautionType): bool
    {
        return $user->hasPermission('caution_types.delete');
    }

    public function restore(User $user, CautionType $cautionType): bool
    {
        return $user->hasPermission('caution_types.restore');
    }

    public function forceDelete(User $user, CautionType $cautionType): bool
    {
        return $user->hasPermission('caution_types.force_delete');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('caution_types.export');
    }
}
