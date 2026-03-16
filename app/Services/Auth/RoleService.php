<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleService
{
    /**
     * Create a new role.
     *
     * @param  array{name: string, slug: string, description?: string, company_id?: int|null, is_system?: bool}  $data
     */
    public function create(array $data): Role
    {
        $this->ensureSlugUnique($data['slug'], $data['company_id'] ?? null);

        return Role::create([
            'company_id'  => $data['company_id'] ?? null,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_system' => $data['is_system'] ?? false,
        ]);
    }

    /**
     * Update an existing role.
     *
     * @param  array{name?: string, slug?: string, description?: string}  $data
     */
    public function update(Role $role, array $data): Role
    {
        if (isset($data['slug']) && $data['slug'] !== $role->slug) {
            $this->ensureSlugUnique($data['slug'], $role->company_id, $role->id);
        }

        $role->update(array_filter([
            'name' => $data['name'] ?? $role->name,
            'slug' => $data['slug'] ?? $role->slug,
            'description' => array_key_exists('description', $data) ? $data['description'] : $role->description,
        ], fn ($v) => $v !== null));

        return $role->fresh();
    }

    /**
     * Delete a role. System roles cannot be deleted.
     */
    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages([
                'role' => 'System roles cannot be deleted.',
            ]);
        }

        DB::transaction(function () use ($role): void {
            $role->permissions()->detach();
            $role->users()->detach();
            $role->delete();
        });
    }

    /**
     * Assign permissions to a role (additive — does not remove existing ones).
     *
     * @param  int[]  $permissionIds
     */
    public function assignPermissions(Role $role, array $permissionIds): Role
    {
        $this->validatePermissionIds($permissionIds);
        $role->permissions()->syncWithoutDetaching($permissionIds);

        return $role->load('permissions');
    }

    /**
     * Revoke specific permissions from a role.
     *
     * @param  int[]  $permissionIds
     */
    public function revokePermissions(Role $role, array $permissionIds): Role
    {
        $role->permissions()->detach($permissionIds);

        return $role->load('permissions');
    }

    /**
     * Replace all permissions on a role with the given set.
     *
     * @param  int[]  $permissionIds
     */
    public function syncPermissions(Role $role, array $permissionIds): Role
    {
        $this->validatePermissionIds($permissionIds);
        $role->permissions()->sync($permissionIds);

        return $role->load('permissions');
    }

    /**
     * Assign a role to a user within a specific company.
     */
    public function assignToUser(Role $role, User $user, Company $company): void
    {
        $this->ensureUserBelongsToCompany($user, $company);

        // syncWithoutDetaching to avoid duplicate pivot rows
        $user->roles()->syncWithoutDetaching([
            $role->id => ['company_id' => $company->id],
        ]);
    }

    /**
     * Remove a role from a user within a specific company.
     */
    public function removeFromUser(Role $role, User $user, Company $company): void
    {
        // Only detach the pivot row matching both role_id and company_id
        $user->roles()
            ->wherePivot('company_id', $company->id)
            ->detach($role->id);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function ensureSlugUnique(string $slug, ?int $companyId, ?int $excludeId = null): void
    {
        $query = Role::where('slug', $slug)
            ->where('company_id', $companyId);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'slug' => "A role with slug '{$slug}' already exists for this company.",
            ]);
        }
    }

    /**
     * @param  int[]  $permissionIds
     */
    private function validatePermissionIds(array $permissionIds): void
    {
        if (empty($permissionIds)) {
            return;
        }

        $found = Permission::whereIn('id', $permissionIds)->count();

        if ($found !== count($permissionIds)) {
            throw ValidationException::withMessages([
                'permissions' => 'One or more permission IDs are invalid.',
            ]);
        }
    }

    private function ensureUserBelongsToCompany(User $user, Company $company): void
    {
        if (!$user->companies()->where('companies.id', $company->id)->exists()) {
            throw ValidationException::withMessages([
                'user' => 'The user does not belong to the specified company.',
            ]);
        }
    }
}
