<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    // ─── hasPermission() — single role ───────────────────────────────────────

    public function test_has_permission_returns_true_when_role_has_permission_in_current_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $permission = Permission::create([
            'module'      => 'invoices',
            'name'        => 'View Invoices',
            'slug'        => 'invoices.view',
            'description' => null,
        ]);

        $role = Role::create([
            'company_id'  => $company->id,
            'name'        => 'Accountant',
            'slug'        => 'accountant',
            'description' => null,
            'is_system'   => false,
        ]);

        $role->permissions()->attach($permission->id);

        $user->roles()->attach($role->id, ['company_id' => $company->id]);

        $this->assertTrue($user->hasPermission('invoices.view'));
    }

    public function test_has_permission_returns_false_when_role_does_not_have_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $role = Role::create([
            'company_id'  => $company->id,
            'name'        => 'Viewer',
            'slug'        => 'viewer',
            'description' => null,
            'is_system'   => false,
        ]);

        // Role has no permissions attached
        $user->roles()->attach($role->id, ['company_id' => $company->id]);

        $this->assertFalse($user->hasPermission('invoices.delete'));
    }

    // ─── hasPermission() — multiple roles ────────────────────────────────────

    public function test_has_permission_returns_true_when_only_one_of_multiple_roles_has_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $permission = Permission::create([
            'module'      => 'invoices',
            'name'        => 'Create Invoice',
            'slug'        => 'invoices.create',
            'description' => null,
        ]);

        $roleWithPermission = Role::create([
            'company_id'  => $company->id,
            'name'        => 'Billing',
            'slug'        => 'billing',
            'description' => null,
            'is_system'   => false,
        ]);
        $roleWithPermission->permissions()->attach($permission->id);

        $roleWithoutPermission = Role::create([
            'company_id'  => $company->id,
            'name'        => 'Support',
            'slug'        => 'support',
            'description' => null,
            'is_system'   => false,
        ]);
        // No permissions on this role

        $user->roles()->attach($roleWithPermission->id, ['company_id' => $company->id]);
        $user->roles()->attach($roleWithoutPermission->id, ['company_id' => $company->id]);

        $this->assertTrue($user->hasPermission('invoices.create'));
    }

    // ─── hasPermission() — no roles ──────────────────────────────────────────

    public function test_has_permission_returns_false_when_user_has_no_roles(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        // User has no roles attached at all
        $this->assertFalse($user->hasPermission('invoices.view'));
    }

    // ─── hasPermission() — cross-company isolation ───────────────────────────

    public function test_has_permission_does_not_leak_permissions_across_companies(): void
    {
        // Company A — user's current company
        ['user' => $user, 'company' => $companyA] = $this->setUpCompanyAndUser();

        // Company B — a second, unrelated company
        $companyB = Company::factory()->create();

        $permission = Permission::create([
            'module'      => 'invoices',
            'name'        => 'Delete Invoice',
            'slug'        => 'invoices.delete',
            'description' => null,
        ]);

        // Role assigned in company B with the permission
        $roleInCompanyB = Role::create([
            'company_id'  => $companyB->id,
            'name'        => 'Admin B',
            'slug'        => 'admin-b',
            'description' => null,
            'is_system'   => false,
        ]);
        $roleInCompanyB->permissions()->attach($permission->id);

        // Attach the role to the user but scoped to company B
        $user->roles()->attach($roleInCompanyB->id, ['company_id' => $companyB->id]);

        // User's current_company_id is still company A
        $this->assertSame($companyA->id, $user->current_company_id);

        // Permission granted in company B must NOT be visible in company A context
        $this->assertFalse($user->hasPermission('invoices.delete'));
    }
}
