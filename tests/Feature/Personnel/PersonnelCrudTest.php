<?php

declare(strict_types=1);

namespace Tests\Feature\Personnel;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Personnel\Department;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\Position;
use Tests\TestCase;

class PersonnelCrudTest extends TestCase
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

    public function test_user_with_permission_can_list_personnel(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['personnels.view_any']);

        Personnel::factory()->create(['company_id' => $company->id, 'first_name' => 'Alice', 'last_name' => 'Smith']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/personnels');

        $response->assertOk()
            ->assertJsonFragment(['first_name' => 'Alice']);
    }

    public function test_index_returns_paginated_results(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['personnels.view_any']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/personnels');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/personnels')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/personnels')
            ->assertForbidden();
    }

    public function test_index_only_returns_personnel_from_current_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['personnels.view_any']);

        Personnel::factory()->create(['company_id' => $company->id, 'first_name' => 'MyEmployee']);

        $otherCompany = Company::factory()->create();
        Personnel::factory()->create(['company_id' => $otherCompany->id, 'first_name' => 'OtherEmployee']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/personnels');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('first_name')->toArray();
        $this->assertContains('MyEmployee', $names);
        $this->assertNotContains('OtherEmployee', $names);
    }

    public function test_index_search_by_name_filter(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['personnels.view_any']);

        Personnel::factory()->create(['company_id' => $company->id, 'first_name' => 'Zara', 'last_name' => 'Jones']);
        Personnel::factory()->create(['company_id' => $company->id, 'first_name' => 'Bob', 'last_name' => 'Brown']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/personnels?name=Zara');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('first_name')->toArray();
        $this->assertContains('Zara', $names);
        $this->assertNotContains('Bob', $names);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_personnel(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['personnels.create']);

        $payload = [
            'first_name'      => 'John',
            'last_name'       => 'Doe',
            'hire_date'       => '2024-01-15',
            'employment_type' => 'full_time',
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/personnels', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['first_name' => 'John', 'last_name' => 'Doe']);

        $this->assertDatabaseHas('personnels', [
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'company_id' => $company->id,
        ]);
    }

    public function test_store_auto_generates_matricule(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['personnels.create']);

        $payload = [
            'first_name'      => 'Jane',
            'last_name'       => 'Doe',
            'hire_date'       => '2024-01-15',
            'employment_type' => 'full_time',
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/personnels', $payload);

        $response->assertCreated();
        $matricule = $response->json('matricule');
        $this->assertNotNull($matricule);
        $this->assertMatchesRegularExpression('/^EMP-\d{4}-\d{5}$/', $matricule);
    }

    public function test_store_requires_first_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['personnels.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/personnels', ['last_name' => 'Doe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name']);
    }

    public function test_store_requires_last_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['personnels.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/personnels', ['first_name' => 'John'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['last_name']);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/personnels', ['first_name' => 'John', 'last_name' => 'Doe'])
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_personnel(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['personnels.view']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/personnels/{$personnel->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $personnel->id]);
    }

    public function test_show_returns_404_for_nonexistent_personnel(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['personnels.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/personnels/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/personnels/{$personnel->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_personnel(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['personnels.update']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id, 'first_name' => 'Old']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/personnels/{$personnel->id}", ['first_name' => 'Updated']);

        $response->assertOk()
            ->assertJsonFragment(['first_name' => 'Updated']);

        $this->assertDatabaseHas('personnels', ['id' => $personnel->id, 'first_name' => 'Updated']);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/personnels/{$personnel->id}", ['first_name' => 'Hacked'])
            ->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_personnel(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['personnels.delete']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/personnels/{$personnel->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('personnels', ['id' => $personnel->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/personnels/{$personnel->id}")
            ->assertForbidden();
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_personnel_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['personnels.view']);
        $this->giveUserPermissions($userB, $companyB, ['personnels.view']);

        $personnel = Personnel::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/personnels/{$personnel->id}")
            ->assertStatus(404);
    }

    public function test_user_from_company_b_cannot_update_personnel_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['personnels.update']);
        $this->giveUserPermissions($userB, $companyB, ['personnels.update']);

        $personnel = Personnel::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/personnels/{$personnel->id}", ['first_name' => 'Hijacked'])
            ->assertStatus(404);
    }

    public function test_user_from_company_b_cannot_delete_personnel_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['personnels.delete']);
        $this->giveUserPermissions($userB, $companyB, ['personnels.delete']);

        $personnel = Personnel::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/personnels/{$personnel->id}")
            ->assertStatus(404);
    }

    public function test_index_does_not_leak_personnel_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['personnels.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['personnels.view_any']);

        Personnel::factory()->create(['company_id' => $companyA->id, 'first_name' => 'EmployeeA']);
        Personnel::factory()->create(['company_id' => $companyB->id, 'first_name' => 'EmployeeB']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/personnels');

        $names = collect($response->json('data'))->pluck('first_name')->toArray();
        $this->assertContains('EmployeeA', $names);
        $this->assertNotContains('EmployeeB', $names);
    }
}
