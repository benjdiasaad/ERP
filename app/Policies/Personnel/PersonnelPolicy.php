<?php

declare(strict_types=1);

namespace App\Policies\Personnel;

use App\Models\Auth\User;
use App\Models\Personnel\Personnel;

class PersonnelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('personnels.view_any');
    }

    public function view(User $user, Personnel $personnel): bool
    {
        return $user->hasPermission('personnels.view')
            && $personnel->company_id === $user->current_company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('personnels.create');
    }

    public function update(User $user, Personnel $personnel): bool
    {
        return $user->hasPermission('personnels.update')
            && $personnel->company_id === $user->current_company_id;
    }

    public function delete(User $user, Personnel $personnel): bool
    {
        return $user->hasPermission('personnels.delete')
            && $personnel->company_id === $user->current_company_id;
    }

    public function restore(User $user, Personnel $personnel): bool
    {
        return $user->hasPermission('personnels.restore')
            && $personnel->company_id === $user->current_company_id;
    }

    public function forceDelete(User $user, Personnel $personnel): bool
    {
        return $user->hasPermission('personnels.force_delete')
            && $personnel->company_id === $user->current_company_id;
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('personnels.export');
    }

    public function import(User $user): bool
    {
        return $user->hasPermission('personnels.import');
    }
}
