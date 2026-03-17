<?php

declare(strict_types=1);

namespace Tests\Feature\Personnel;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Personnel\Department;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function giveUserPermissions(User $user, Company $company, array $permissionSlugs): void
    {
        $role = Role::create([
            'company_id' => $company->id,
            'name'       => 'Test Role',
            'slug'       => 'test-role-' . uniqid(),
            'is_system'  => false,
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['module' => explode('.', $slug)[0], 'name' => $slug, 'description' => '']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->syncWithoutDetaching([$role->id => ['company_id' => $company->id]]);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_departments_as_tree(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['departments.view_any']);

        $parent = Department::factory()->create(['company_id' => $company->id, 'name' => 'Engineering']);
        Department::factory()->create(['company_id' => $company->id, 'parent_id' => $parent->id, 'name' => 'Backend']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/departments');

        $response->assertOk();
        $data = $response->json();
        // Root departments should be returned (no parent_id)
        $rootNames = collect($data)->pluck('name')->toArray();
        $this->assertContains('Engineering', $rootNames);
    }

    public function test_index_tree_includes_children(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['departments.view_any']);

        $parent = Department::factory()->create(['company_id' => $company->id, 'name' => 'HR']);
        Department::factory()->create(['company_id' => $company->id, 'parent_id' => $parent->id, 'name' => 'Recruitment']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/departments');

        $response->assertOk();
        $hrDept = collect($response->json())->firstWhere('name', 'HR');
        $this->assertNotNull($hrDept);
        $childNames = collect($hrDept['children'])->pluck('name')->toArray();
        $this->assertContains('Recruitment', $childNames);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/departments')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/departments')
            ->assertForbidden();
    }

    public function test_index_only_returns_departments_from_current_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['departments.view_any']);

        Department::factory()->create(['company_id' => $company->id, 'name' => 'MyDept']);

        $otherCompany = Company::factory()->create();
        Department::factory()->create(['company_id' => $otherCompany->id, 'name' => 'OtherDept']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/departments');

        $response->assertOk();
        $names = collect($response->json())->pluck('name')->toArray();
        $this->assertContains('MyDept', $names);
        $this->assertNotContains('OtherDept', $names);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_department(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['departments.create']);

        $payload = ['name' => 'Finance', 'code' => 'FIN-001'];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/departments', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Finance']);

        $this->assertDatabaseHas('departments', [
            'name'       => 'Finance',
            'code'       => 'FIN-001',
            'company_id' => $company->id,
        ]);
    }

    public function test_store_requires_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['departments.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/departments', ['code' => 'NO-NAME'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_code(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['departments.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/departments', ['name' => 'No Code Dept'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_store_can_create_child_department_with_parent_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['departments.create']);

        $parent = Department::factory()->create(['company_id' => $company->id]);

        $payload = ['name' => 'Sub Team', 'code' => 'SUB-001', 'parent_id' => $parent->id];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/departments', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('departments', [
            'name'      => 'Sub Team',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/departments', ['name' => 'Dept', 'code' => 'D-001'])
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_department(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['departments.view']);

        $department = Department::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/departments/{$department->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $department->id])
            ->assertJsonStructure(['parent', 'manager', 'children']);
    }

    public function test_show_returns_404_for_nonexistent_department(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['departments.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/departments/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $department = Department::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/departments/{$department->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_department(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['departments.update']);

        $department = Department::factory()->create(['company_id' => $company->id, 'name' => 'Old Name']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/departments/{$department->id}", ['name' => 'New Name']);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'New Name']);

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'New Name']);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $department = Department::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/departments/{$department->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_department(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['departments.delete']);

        $department = Department::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/departments/{$department->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('departments', ['id' => $department->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $department = Department::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/departments/{$department->id}")
            ->assertForbidden();
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_department_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['departments.view']);
        $this->giveUserPermissions($userB, $companyB, ['departments.view']);

        $department = Department::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/departments/{$department->id}")
            ->assertStatus(404);
    }

    public function test_user_from_company_b_cannot_update_department_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['departments.update']);
        $this->giveUserPermissions($userB, $companyB, ['departments.update']);

        $department = Department::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/departments/{$department->id}", ['name' => 'Hijacked'])
            ->assertStatus(404);
    }

    public function test_user_from_company_b_cannot_delete_department_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['departments.delete']);
        $this->giveUserPermissions($userB, $companyB, ['departments.delete']);

        $department = Department::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/departments/{$department->id}")
            ->assertStatus(404);
    }
}
