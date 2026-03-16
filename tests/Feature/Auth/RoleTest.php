<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use Tests\TestCase;

class RoleTest extends TestCase
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Create a role with the given permissions and attach it to the user
     * in the given company, so the user can call the roles API.
     *
     * @param  string[]  $permissionSlugs
     */
    private function giveUserPermissions(User $user, Company $company, array $permissionSlugs): Role
    {
        $role = Role::create([
            'company_id'  => $company->id,
            'name'        => 'Test Role',
            'slug'        => 'test-role-' . uniqid(),
            'description' => 'Auto-created for tests',
            'is_system'   => false,
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                [
                    'module'      => explode('.', $slug)[0],
                    'name'        => $slug,
                    'description' => '',
                ]
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->syncWithoutDetaching([
            $role->id => ['company_id' => $company->id],
        ]);

        return $role;
    }

    /**
     * Create a company-scoped role (not system, not the user's own role).
     */
    private function createCompanyRole(Company $company, array $overrides = []): Role
    {
        return Role::create(array_merge([
            'company_id'  => $company->id,
            'name'        => 'Custom Role',
            'slug'        => 'custom-role-' . uniqid(),
            'description' => 'A custom role',
            'is_system'   => false,
        ], $overrides));
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_roles(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.view_any']);

        $role = $this->createCompanyRole($company, ['name' => 'Sales Manager']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/roles');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Sales Manager']);
    }

    public function test_index_returns_paginated_results(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.view_any']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/roles');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta', 'links']);
    }

    public function test_index_only_returns_roles_for_current_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.view_any']);

        $this->createCompanyRole($company, ['name' => 'My Company Role']);

        // Role belonging to another company
        $otherCompany = Company::factory()->create();
        $this->createCompanyRole($otherCompany, ['name' => 'Other Company Role']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/roles');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('My Company Role', $names);
        $this->assertNotContains('Other Company Role', $names);
    }

    public function test_index_includes_global_roles(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.view_any']);

        // Global role (company_id = null)
        Role::create([
            'company_id'  => null,
            'name'        => 'Global Admin',
            'slug'        => 'global-admin-' . uniqid(),
            'is_system'   => true,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/roles');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Global Admin', $names);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/roles')->assertUnauthorized();
    }

    public function test_index_requires_roles_view_any_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        // No permissions granted

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/roles')
            ->assertForbidden();
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_a_role(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.create']);

        $payload = [
            'name'        => 'Finance Manager',
            'slug'        => 'finance-manager',
            'description' => 'Manages finance module',
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/roles', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'name'       => 'Finance Manager',
                'slug'       => 'finance-manager',
                'company_id' => $company->id,
            ]);

        $this->assertDatabaseHas('roles', [
            'name'       => 'Finance Manager',
            'slug'       => 'finance-manager',
            'company_id' => $company->id,
        ]);
    }

    public function test_store_requires_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/roles', ['slug' => 'no-name'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_slug(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/roles', ['name' => 'No Slug Role'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_validates_slug_format(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/roles', ['name' => 'Bad Slug', 'slug' => 'Bad Slug!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_store_rejects_duplicate_slug_within_same_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.create']);

        $this->createCompanyRole($company, ['slug' => 'duplicate-slug']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/roles', ['name' => 'Another Role', 'slug' => 'duplicate-slug'])
            ->assertUnprocessable();
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        // No permissions

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/roles', ['name' => 'Role', 'slug' => 'role'])
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_a_role(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.view']);

        $role = $this->createCompanyRole($company, ['name' => 'Viewable Role']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/roles/{$role->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id'   => $role->id,
                'name' => 'Viewable Role',
            ]);
    }

    public function test_show_includes_permissions(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.view']);

        $role = $this->createCompanyRole($company);
        $permission = Permission::firstOrCreate(
            ['slug' => 'invoices.view'],
            ['module' => 'invoices', 'name' => 'View Invoices', 'description' => '']
        );
        $role->permissions()->attach($permission->id);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/roles/{$role->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['permissions']]);

        $permSlugs = collect($response->json('data.permissions'))->pluck('slug')->toArray();
        $this->assertContains('invoices.view', $permSlugs);
    }

    public function test_show_returns_404_for_nonexistent_role(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/roles/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        // No permissions

        $role = $this->createCompanyRole($company);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/roles/{$role->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_a_role(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.update']);

        $role = $this->createCompanyRole($company, ['name' => 'Old Name']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/roles/{$role->id}", ['name' => 'New Name']);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'New Name']);
    }

    public function test_update_validates_slug_format(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.update']);

        $role = $this->createCompanyRole($company);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/roles/{$role->id}", ['slug' => 'Invalid Slug!'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_update_rejects_duplicate_slug_within_same_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.update']);

        $this->createCompanyRole($company, ['slug' => 'existing-slug']);
        $role = $this->createCompanyRole($company, ['slug' => 'my-role']);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/roles/{$role->id}", ['slug' => 'existing-slug'])
            ->assertUnprocessable();
    }

    public function test_cannot_update_system_role(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.update']);

        $systemRole = Role::create([
            'company_id' => null,
            'name'       => 'System Role',
            'slug'       => 'system-role-' . uniqid(),
            'is_system'  => true,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/roles/{$systemRole->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        // No permissions

        $role = $this->createCompanyRole($company);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/roles/{$role->id}", ['name' => 'New Name'])
            ->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_delete_a_role(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.delete']);

        $role = $this->createCompanyRole($company);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/roles/{$role->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_system_role(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.delete']);

        $systemRole = Role::create([
            'company_id' => null,
            'name'       => 'Super Admin',
            'slug'       => 'super-admin-' . uniqid(),
            'is_system'  => true,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/roles/{$systemRole->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $systemRole->id]);
    }

    public function test_delete_detaches_permissions_and_users(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.delete']);

        $role = $this->createCompanyRole($company);

        $permission = Permission::firstOrCreate(
            ['slug' => 'products.view'],
            ['module' => 'products', 'name' => 'View Products', 'description' => '']
        );
        $role->permissions()->attach($permission->id);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/roles/{$role->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('permission_role', ['role_id' => $role->id]);
    }

    public function test_delete_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        // No permissions

        $role = $this->createCompanyRole($company);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/roles/{$role->id}")
            ->assertForbidden();
    }

    // ─── Assign Permissions ───────────────────────────────────────────────────

    public function test_can_assign_permissions_to_a_role(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.update']);

        $role = $this->createCompanyRole($company);

        $perm1 = Permission::firstOrCreate(
            ['slug' => 'invoices.create'],
            ['module' => 'invoices', 'name' => 'Create Invoices', 'description' => '']
        );
        $perm2 = Permission::firstOrCreate(
            ['slug' => 'invoices.view'],
            ['module' => 'invoices', 'name' => 'View Invoices', 'description' => '']
        );

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/roles/{$role->id}/permissions", [
                'permission_ids' => [$perm1->id, $perm2->id],
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $perm1->id]);
        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $perm2->id]);
    }

    public function test_assign_permissions_is_additive(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.update']);

        $role = $this->createCompanyRole($company);

        $perm1 = Permission::firstOrCreate(
            ['slug' => 'quotes.view'],
            ['module' => 'quotes', 'name' => 'View Quotes', 'description' => '']
        );
        $perm2 = Permission::firstOrCreate(
            ['slug' => 'quotes.create'],
            ['module' => 'quotes', 'name' => 'Create Quotes', 'description' => '']
        );

        // Assign first permission
        $role->permissions()->attach($perm1->id);

        // Assign second permission additively
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/roles/{$role->id}/permissions", [
                'permission_ids' => [$perm2->id],
            ]);

        // Both should still be present
        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $perm1->id]);
        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $perm2->id]);
    }

    public function test_assign_permissions_validates_permission_ids(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.update']);

        $role = $this->createCompanyRole($company);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/roles/{$role->id}/permissions", [
                'permission_ids' => [99999],
            ])
            ->assertUnprocessable();
    }

    public function test_assign_permissions_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        // No permissions

        $role = $this->createCompanyRole($company);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/roles/{$role->id}/permissions", [
                'permission_ids' => [],
            ])
            ->assertForbidden();
    }

    public function test_cannot_assign_permissions_to_system_role(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.update']);

        $systemRole = Role::create([
            'company_id' => null,
            'name'       => 'System',
            'slug'       => 'system-' . uniqid(),
            'is_system'  => true,
        ]);

        $perm = Permission::firstOrCreate(
            ['slug' => 'users.view'],
            ['module' => 'users', 'name' => 'View Users', 'description' => '']
        );

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/roles/{$systemRole->id}/permissions", [
                'permission_ids' => [$perm->id],
            ])
            ->assertForbidden();
    }

    // ─── Revoke Permissions ───────────────────────────────────────────────────

    public function test_can_revoke_permissions_from_a_role(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.update']);

        $role = $this->createCompanyRole($company);

        $perm1 = Permission::firstOrCreate(
            ['slug' => 'customers.view'],
            ['module' => 'customers', 'name' => 'View Customers', 'description' => '']
        );
        $perm2 = Permission::firstOrCreate(
            ['slug' => 'customers.create'],
            ['module' => 'customers', 'name' => 'Create Customers', 'description' => '']
        );

        $role->permissions()->attach([$perm1->id, $perm2->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/roles/{$role->id}/permissions", [
                'permission_ids' => [$perm1->id],
            ]);

        $response->assertOk();

        $this->assertDatabaseMissing('permission_role', ['role_id' => $role->id, 'permission_id' => $perm1->id]);
        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $perm2->id]);
    }

    public function test_revoke_permissions_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        // No permissions

        $role = $this->createCompanyRole($company);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/roles/{$role->id}/permissions", [
                'permission_ids' => [],
            ])
            ->assertForbidden();
    }

    public function test_cannot_revoke_permissions_from_system_role(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.update']);

        $systemRole = Role::create([
            'company_id' => null,
            'name'       => 'System Revoke Test',
            'slug'       => 'system-revoke-' . uniqid(),
            'is_system'  => true,
        ]);

        $perm = Permission::firstOrCreate(
            ['slug' => 'roles.view'],
            ['module' => 'roles', 'name' => 'View Roles', 'description' => '']
        );
        $systemRole->permissions()->attach($perm->id);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/roles/{$systemRole->id}/permissions", [
                'permission_ids' => [$perm->id],
            ])
            ->assertForbidden();
    }

    // ─── Sync Permissions ─────────────────────────────────────────────────────

    public function test_can_sync_permissions_on_a_role(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.update']);

        $role = $this->createCompanyRole($company);

        $perm1 = Permission::firstOrCreate(
            ['slug' => 'suppliers.view'],
            ['module' => 'suppliers', 'name' => 'View Suppliers', 'description' => '']
        );
        $perm2 = Permission::firstOrCreate(
            ['slug' => 'suppliers.create'],
            ['module' => 'suppliers', 'name' => 'Create Suppliers', 'description' => '']
        );
        $perm3 = Permission::firstOrCreate(
            ['slug' => 'suppliers.update'],
            ['module' => 'suppliers', 'name' => 'Update Suppliers', 'description' => '']
        );

        // Start with perm1 and perm2
        $role->permissions()->attach([$perm1->id, $perm2->id]);

        // Sync to perm2 and perm3 only
        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/roles/{$role->id}/permissions", [
                'permission_ids' => [$perm2->id, $perm3->id],
            ]);

        $response->assertOk();

        $this->assertDatabaseMissing('permission_role', ['role_id' => $role->id, 'permission_id' => $perm1->id]);
        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $perm2->id]);
        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $perm3->id]);
    }

    public function test_sync_permissions_replaces_all_existing_permissions(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['roles.update']);

        $role = $this->createCompanyRole($company);

        $perm1 = Permission::firstOrCreate(
            ['slug' => 'warehouses.view'],
            ['module' => 'warehouses', 'name' => 'View Warehouses', 'description' => '']
        );
        $perm2 = Permission::firstOrCreate(
            ['slug' => 'warehouses.create'],
            ['module' => 'warehouses', 'name' => 'Create Warehouses', 'description' => '']
        );
        $perm3 = Permission::firstOrCreate(
            ['slug' => 'warehouses.update'],
            ['module' => 'warehouses', 'name' => 'Update Warehouses', 'description' => '']
        );

        $role->permissions()->attach([$perm1->id, $perm2->id]);

        // Sync to only perm3 — perm1 and perm2 should be removed
        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/roles/{$role->id}/permissions", [
                'permission_ids' => [$perm3->id],
            ]);

        $response->assertOk();

        $this->assertDatabaseMissing('permission_role', ['role_id' => $role->id, 'permission_id' => $perm1->id]);
        $this->assertDatabaseMissing('permission_role', ['role_id' => $role->id, 'permission_id' => $perm2->id]);
        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $perm3->id]);
    }

    public function test_sync_permissions_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        // No permissions

        $role = $this->createCompanyRole($company);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/roles/{$role->id}/permissions", [
                'permission_ids' => [],
            ])
            ->assertForbidden();
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_cannot_view_role_from_another_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['roles.view']);
        $this->giveUserPermissions($userB, $companyB, ['roles.view']);

        $roleA = $this->createCompanyRole($companyA, ['name' => 'Company A Role']);

        // userB tries to access companyA's role
        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/roles/{$roleA->id}")
            ->assertStatus(403);
    }

    public function test_user_cannot_update_role_from_another_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['roles.update']);
        $this->giveUserPermissions($userB, $companyB, ['roles.update']);

        $roleA = $this->createCompanyRole($companyA);

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/roles/{$roleA->id}", ['name' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_user_cannot_delete_role_from_another_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['roles.delete']);
        $this->giveUserPermissions($userB, $companyB, ['roles.delete']);

        $roleA = $this->createCompanyRole($companyA);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/roles/{$roleA->id}")
            ->assertForbidden();
    }

    public function test_index_does_not_leak_roles_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['roles.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['roles.view_any']);

        $this->createCompanyRole($companyA, ['name' => 'Role A Only']);
        $this->createCompanyRole($companyB, ['name' => 'Role B Only']);

        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/roles');

        $namesA = collect($responseA->json('data'))->pluck('name')->toArray();
        $this->assertContains('Role A Only', $namesA);
        $this->assertNotContains('Role B Only', $namesA);
    }

    public function test_user_cannot_assign_permissions_to_role_from_another_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['roles.update']);
        $this->giveUserPermissions($userB, $companyB, ['roles.update']);

        $roleA = $this->createCompanyRole($companyA);

        $perm = Permission::firstOrCreate(
            ['slug' => 'contacts.view'],
            ['module' => 'contacts', 'name' => 'View Contacts', 'description' => '']
        );

        $this->withHeaders($this->authHeaders($userB))
            ->postJson("/api/roles/{$roleA->id}/permissions", [
                'permission_ids' => [$perm->id],
            ])
            ->assertForbidden();
    }
}
