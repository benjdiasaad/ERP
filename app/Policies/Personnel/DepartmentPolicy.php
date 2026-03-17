<?php

declare(strict_types=1);

namespace App\Policies\Personnel;

use App\Models\Auth\User;
use App\Models\Personnel\Department;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('departments.view_any');
    }

    public function view(User $user, Department $department): bool
    {
        return $user->hasPermission('departments.view')
            && $department->company_id === $user->current_company_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('departments.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasPermission('departments.update')
            && $department->company_id === $user->current_company_id;
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->hasPermission('departments.delete')
            && $department->company_id === $user->current_company_id;
    }

    public function restore(User $user, Department $department): bool
    {
        return $user->hasPermission('departments.restore')
            && $department->company_id === $user->current_company_id;
    }

    public function forceDelete(User $user, Department $department): bool
    {
        return $user->hasPermission('departments.force_delete')
            && $department->company_id === $user->current_company_id;
    }
}
