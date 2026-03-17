<?php

declare(strict_types=1);

namespace Tests\Feature\Personnel;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Personnel\Contract;
use App\Models\Personnel\Personnel;
use Tests\TestCase;

class ContractTest extends TestCase
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function giveUserPermissions(User $user, Company $company, array $permissionSlugs): void
    {
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Test Role',
            'slug' => 'test-role-' . uniqid(),
            'is_system'=> false,
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

    public function test_user_with_permission_can_list_contracts(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['contracts.view_any']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        Contract::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/contracts');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
    }

    public function test_index_filter_by_personnel_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['contracts.view_any']);

        $personnelA = Personnel::factory()->create(['company_id' => $company->id]);

        $personnelB = Personnel::factory()->create(['company_id' => $company->id]);

        $contractA = Contract::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnelA->id]);
        
        Contract::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnelB->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/contracts?personnel_id={$personnelA->id}");

        $response->assertOk();
        
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        
        $this->assertContains($contractA->id, $ids);
        
        foreach ($ids as $id) {
            $this->assertEquals($contractA->personnel_id, Contract::find($id)->personnel_id);
        }

    }

    public function test_index_filter_by_type(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($user, $company, ['contracts.view_any']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        
        Contract::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'type' => 'CDI']);
        
        Contract::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'type' => 'CDD']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/contracts?type=CDI');

        $response->assertOk();
        
        $types = collect($response->json('data'))->pluck('type')->toArray();
        
        $this->assertNotContains('CDD', $types);
        
        foreach ($types as $type) {
            $this->assertEquals('CDI', $type);
        }

    }

    public function test_index_filter_by_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        
        $this->giveUserPermissions($user, $company, ['contracts.view_any']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        
        Contract::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'status' => 'active']);
        
        Contract::factory()->draft()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/contracts?status=draft');

        $response->assertOk();
        
        $statuses = collect($response->json('data'))->pluck('status')->toArray();
        
        foreach ($statuses as $status) {
            $this->assertEquals('draft', $status);
        }
        
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/contracts')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/contracts')
            ->assertForbidden();
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_contract(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['contracts.create']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $payload = [
            'personnel_id' => $personnel->id,
            'type'       => 'CDI',
            'start_date' => '2024-01-01',
            'salary'     => 5000,
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/contracts', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['type' => 'CDI']);

        $this->assertDatabaseHas('contracts', [
            'personnel_id' => $personnel->id,
            'type'       => 'CDI',
            'company_id' => $company->id,
        ]);
    }

    public function test_store_requires_personnel_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['contracts.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/contracts', ['type' => 'CDI', 'start_date' => '2024-01-01', 'salary' => 5000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['personnel_id']);
    }

    public function test_store_requires_type(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['contracts.create']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/contracts', ['personnel_id' => $personnel->id, 'start_date' => '2024-01-01', 'salary' => 5000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_requires_start_date(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['contracts.create']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/contracts', ['personnel_id' => $personnel->id, 'type' => 'CDI', 'salary' => 5000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_store_requires_salary(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['contracts.create']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/contracts', ['personnel_id' => $personnel->id, 'type' => 'CDI', 'start_date' => '2024-01-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['salary']);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/contracts', ['personnel_id' => $personnel->id, 'type' => 'CDI', 'start_date' => '2024-01-01', 'salary' => 5000])
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_contract_with_personnel(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['contracts.view']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $contract= Contract::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/contracts/{$contract->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $contract->id])
            ->assertJsonStructure(['personnel']);
    }

    public function test_show_returns_404_for_nonexistent_contract(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['contracts.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/contracts/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $contract= Contract::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/contracts/{$contract->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_contract(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['contracts.update']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $contract= Contract::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id, 'salary' => 4000]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/contracts/{$contract->id}", ['salary' => 6000]);

        $response->assertOk();
        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'salary' => 6000]);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $contract= Contract::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/contracts/{$contract->id}", ['salary' => 9999])
            ->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_contract(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['contracts.delete']);

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $contract= Contract::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/contracts/{$contract->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('contracts', ['id' => $contract->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $personnel = Personnel::factory()->create(['company_id' => $company->id]);
        $contract= Contract::factory()->create(['company_id' => $company->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/contracts/{$contract->id}")
            ->assertForbidden();
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_contract_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['contracts.view']);
        $this->giveUserPermissions($userB, $companyB, ['contracts.view']);

        $personnel = Personnel::factory()->create(['company_id' => $companyA->id]);
        $contract= Contract::factory()->create(['company_id' => $companyA->id, 'personnel_id' => $personnel->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/contracts/{$contract->id}")
            ->assertStatus(404);
    }

    public function test_index_does_not_leak_contracts_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['contracts.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['contracts.view_any']);

        $personnelA = Personnel::factory()->create(['company_id' => $companyA->id]);
        $personnelB = Personnel::factory()->create(['company_id' => $companyB->id]);

        $contractA = Contract::factory()->create(['company_id' => $companyA->id, 'personnel_id' => $personnelA->id]);
        $contractB = Contract::factory()->create(['company_id' => $companyB->id, 'personnel_id' => $personnelB->id]);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/contracts');

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($contractA->id, $ids);
        $this->assertNotContains($contractB->id, $ids);
    }
}
