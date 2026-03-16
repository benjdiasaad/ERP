<?php

declare(strict_types=1);

namespace App\Policies\Auth;

use App\Models\Auth\Role;
use App\Models\Auth\User;

class RolePolicy
{
    /**
     * Determine whether the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view_any');
    }

    /**
     * Determine whether the user can view the role.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.view')
            && $this->isRoleAccessible($user, $role);
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('roles.create');
    }

    /**
     * Determine whether the user can update the role.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.update')
            && $this->isRoleAccessible($user, $role)
            && ! $role->is_system;
    }

    /**
     * Determine whether the user can delete the role.
     * System roles cannot be deleted by anyone.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.delete')
            && $this->isRoleAccessible($user, $role)
            && ! $role->is_system;
    }

    /**
     * Determine whether the user can restore the role.
     */
    public function restore(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.restore')
            && $this->isRoleAccessible($user, $role)
            && ! $role->is_system;
    }

    /**
     * Determine whether the user can permanently delete the role.
     * System roles cannot be force-deleted by anyone.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.force_delete')
            && $this->isRoleAccessible($user, $role)
            && ! $role->is_system;
    }

    /**
     * Determine whether the user can assign permissions to the role.
     */
    public function assignPermissions(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.update')
            && $this->isRoleAccessible($user, $role)
            && ! $role->is_system;
    }

    /**
     * Determine whether the user can revoke permissions from the role.
     */
    public function revokePermissions(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.update')
            && $this->isRoleAccessible($user, $role)
            && ! $role->is_system;
    }

    /**
     * Check if the role is accessible to the user.
     * A role is accessible if it is global (company_id is null)
     * or belongs to the user's current company.
     */
    private function isRoleAccessible(User $user, Role $role): bool
    {
        return is_null($role->company_id)
            || $role->company_id === $user->current_company_id;
    }
}
