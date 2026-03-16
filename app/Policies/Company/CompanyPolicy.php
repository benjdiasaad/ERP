<?php

declare(strict_types=1);

namespace App\Policies\Company;

use App\Models\Company\Company;
use App\Models\Auth\User;

class CompanyPolicy
{
    /**
     * Determine whether the user can view any companies.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('companies.view_any');
    }

    /**
     * Determine whether the user can view the company.
     */
    public function view(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.view')
            && $user->companies()->where('companies.id', $company->id)->exists();
    }

    /**
     * Determine whether the user can create companies.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('companies.create');
    }

    /**
     * Determine whether the user can update the company.
     */
    public function update(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.update')
            && $user->companies()->where('companies.id', $company->id)->exists();
    }

    /**
     * Determine whether the user can delete the company.
     */
    public function delete(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.delete')
            && $user->companies()->where('companies.id', $company->id)->exists();
    }

    /**
     * Determine whether the user can restore the company.
     */
    public function restore(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.restore');
    }

    /**
     * Determine whether the user can permanently delete the company.
     */
    public function forceDelete(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.force_delete');
    }

    /**
     * Determine whether the user can add members to the company.
     */
    public function addUser(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.update')
            && $user->companies()->where('companies.id', $company->id)->exists();
    }

    /**
     * Determine whether the user can remove members from the company.
     */
    public function removeUser(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.update')
            && $user->companies()->where('companies.id', $company->id)->exists();
    }

    /**
     * Determine whether the user can switch to the company.
     */
    public function switch(User $user, Company $company): bool
    {
        return $user->companies()->where('companies.id', $company->id)->exists();
    }
}
