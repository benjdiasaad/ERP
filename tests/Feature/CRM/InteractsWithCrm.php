<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use Illuminate\Support\Facades\DB;

trait InteractsWithCrm
{
    private function giveUserPermissions(User $user, Company $company, array $slugs): void
    {
        $role = Role::create([
            'company_id' => $company->id,
            'name'       => 'Test Role',
            'slug'       => 'test-role-' . uniqid(),
            'is_system'  => false,
        ]);

        foreach ($slugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['module' => explode('.', $slug)[0], 'name' => $slug, 'description' => '']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        DB::table('role_user')->insert([
            'role_id'    => $role->id,
            'user_id'    => $user->id,
            'company_id' => $company->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Standard CRUD permissions plus the given extra actions for a module.
     */
    private function modulePermissions(string $module, array $extra = []): array
    {
        return array_map(
            fn (string $action) => "{$module}.{$action}",
            array_merge(['view_any', 'view', 'create', 'update', 'delete'], $extra)
        );
    }

    /**
     * Company + user with the given permissions, plus auth headers.
     *
     * @return array{user: User, company: Company, headers: array<string, string>}
     */
    private function actingWithPermissions(array $slugs): array
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $slugs);

        return ['user' => $user, 'company' => $company, 'headers' => $this->authHeaders($user)];
    }
}
