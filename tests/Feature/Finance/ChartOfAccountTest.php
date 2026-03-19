<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Finance\ChartOfAccount;
use Tests\TestCase;

class ChartOfAccountTest extends TestCase
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

    private function accountPayload(string $code = 'ACC-001', string $type = 'asset'): array
    {
        return [
            'code'        => $code,
            'name'        => 'Test Account',
            'type'        => $type,
            'description' => 'Test account description',
            'is_active'   => true,
            'balance'     => 0,
        ];
    }

    private function allAccountPermissions(): array
    {
        return [
            'chart_of_accounts.view_any',
            'chart_of_accounts.view',
            'chart_of_accounts.create',
            'chart_of_accounts.update',
            'chart_of_accounts.delete',
            'chart_of_accounts.restore',
            'chart_of_accounts.force_delete',
            'chart_of_accounts.export',
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_chart_of_accounts(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.view_any']);

        ChartOfAccount::factory()->count(3)->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/chart-of-accounts');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'code', 'name', 'type']]]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/chart-of-accounts');

        $response->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/chart-of-accounts');

        $response->assertForbidden();
    }

    public function test_index_only_returns_accounts_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['chart_of_accounts.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['chart_of_accounts.view_any']);

        ChartOfAccount::factory()->create(['company_id' => $companyA->id, 'code' => 'ACC-001']);
        ChartOfAccount::factory()->create(['company_id' => $companyB->id, 'code' => 'ACC-002']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/chart-of-accounts');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('ACC-001', $response->json('data.0.code'));
    }

    public function test_index_can_filter_by_type(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.view_any']);

        ChartOfAccount::factory()->create(['company_id' => $company->id, 'type' => 'asset']);
        ChartOfAccount::factory()->create(['company_id' => $company->id, 'type' => 'liability']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/chart-of-accounts?type=asset');

        $response->assertOk();
        $types = collect($response->json('data'))->pluck('type')->unique()->toArray();
        $this->assertEquals(['asset'], $types);
    }

    public function test_index_can_filter_by_is_active(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.view_any']);

        ChartOfAccount::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        ChartOfAccount::factory()->create(['company_id' => $company->id, 'is_active' => false]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/chart-of-accounts?is_active=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_active'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_account(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.create']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/chart-of-accounts', $this->accountPayload());

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'ACC-001');
        $response->assertJsonPath('data.type', 'asset');

        $this->assertDatabaseHas('chart_of_accounts', [
            'company_id' => $company->id,
            'code'       => 'ACC-001',
        ]);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/chart-of-accounts', $this->accountPayload());

        $response->assertForbidden();
    }

    public function test_store_requires_code(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.create']);

        $payload = $this->accountPayload();
        unset($payload['code']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/chart-of-accounts', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('code');
    }

    public function test_store_requires_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.create']);

        $payload = $this->accountPayload();
        unset($payload['name']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/chart-of-accounts', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_requires_type(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.create']);

        $payload = $this->accountPayload();
        unset($payload['type']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/chart-of-accounts', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('type');
    }

    public function test_store_type_must_be_valid(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.create']);

        $payload = $this->accountPayload('ACC-001', 'invalid_type');

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/chart-of-accounts', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('type');
    }

    public function test_store_can_set_parent_account(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.create']);

        $parent = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        $payload = $this->accountPayload();
        $payload['parent_id'] = $parent->id;

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/chart-of-accounts', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_store_rejects_nonexistent_parent(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.create']);

        $payload = $this->accountPayload();
        $payload['parent_id'] = 99999;

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/chart-of-accounts', $payload);

        $response->assertUnprocessable();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_account(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.view']);

        $account = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/chart-of-accounts/{$account->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $account->id);
        $response->assertJsonPath('data.code', $account->code);
    }

    public function test_show_returns_404_for_nonexistent_account(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.view']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/chart-of-accounts/99999');

        $response->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $account = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/chart-of-accounts/{$account->id}");

        $response->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_account(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.update']);

        $account = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/chart-of-accounts/{$account->id}", [
                'name'        => 'Updated Name',
                'description' => 'Updated description',
                'is_active'   => false,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Name');
        $response->assertJsonPath('data.description', 'Updated description');
        $this->assertFalse($response->json('data.is_active'));

        $this->assertDatabaseHas('chart_of_accounts', [
            'id'   => $account->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $account = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/chart-of-accounts/{$account->id}", ['name' => 'Hacked']);

        $response->assertForbidden();
    }

    public function test_update_can_change_parent(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.update']);

        $account = ChartOfAccount::factory()->create(['company_id' => $company->id, 'parent_id' => null]);
        $newParent = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/chart-of-accounts/{$account->id}", ['parent_id' => $newParent->id]);

        $response->assertOk();
        $response->assertJsonPath('data.parent_id', $newParent->id);
    }

    public function test_update_prevents_circular_hierarchy(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.update']);

        $parent = ChartOfAccount::factory()->create(['company_id' => $company->id, 'parent_id' => null]);
        $child = ChartOfAccount::factory()->create(['company_id' => $company->id, 'parent_id' => $parent->id]);

        // Try to set child as parent of parent (circular)
        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/chart-of-accounts/{$parent->id}", ['parent_id' => $child->id]);

        $response->assertUnprocessable();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_account(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.delete']);

        $account = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/chart-of-accounts/{$account->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('chart_of_accounts', ['id' => $account->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $account = ChartOfAccount::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/chart-of-accounts/{$account->id}");

        $response->assertForbidden();
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_account_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['chart_of_accounts.view']);

        $account = ChartOfAccount::factory()->create(['company_id' => $companyA->id]);

        $this->assertTenantIsolation('/api/chart-of-accounts', $userA, $userB, $account->id);
    }

    public function test_user_from_company_b_cannot_update_account_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['chart_of_accounts.update']);

        $account = ChartOfAccount::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/chart-of-accounts/{$account->id}", ['name' => 'Hacked']);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_user_from_company_b_cannot_delete_account_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['chart_of_accounts.delete']);

        $account = ChartOfAccount::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/chart-of-accounts/{$account->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_index_does_not_leak_accounts_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['chart_of_accounts.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['chart_of_accounts.view_any']);

        ChartOfAccount::factory()->count(2)->create(['company_id' => $companyA->id]);
        ChartOfAccount::factory()->count(3)->create(['company_id' => $companyB->id]);

        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/chart-of-accounts');

        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson('/api/chart-of-accounts');

        $responseA->assertOk();
        $responseB->assertOk();
        $this->assertCount(2, $responseA->json('data'));
        $this->assertCount(3, $responseB->json('data'));
    }

    // ─── Search ───────────────────────────────────────────────────────────────

    public function test_search_by_code_returns_matching_accounts(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.view_any']);

        ChartOfAccount::factory()->create(['company_id' => $company->id, 'code' => 'ACC-001']);
        ChartOfAccount::factory()->create(['company_id' => $company->id, 'code' => 'ACC-002']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/chart-of-accounts?search=ACC-001');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('ACC-001', $response->json('data.0.code'));
    }

    public function test_search_by_name_returns_matching_accounts(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.view_any']);

        ChartOfAccount::factory()->create(['company_id' => $company->id, 'name' => 'Cash Account']);
        ChartOfAccount::factory()->create(['company_id' => $company->id, 'name' => 'Bank Account']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/chart-of-accounts?search=Cash');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Cash Account', $response->json('data.0.name'));
    }

    // ─── Hierarchy ────────────────────────────────────────────────────────────

    public function test_account_can_have_children(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.view']);

        $parent = ChartOfAccount::factory()->create(['company_id' => $company->id]);
        $child1 = ChartOfAccount::factory()->create(['company_id' => $company->id, 'parent_id' => $parent->id]);
        $child2 = ChartOfAccount::factory()->create(['company_id' => $company->id, 'parent_id' => $parent->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/chart-of-accounts/{$parent->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data.children'));
    }

    public function test_tree_endpoint_returns_hierarchical_structure(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['chart_of_accounts.view_any']);

        $parent = ChartOfAccount::factory()->create(['company_id' => $company->id, 'parent_id' => null]);
        ChartOfAccount::factory()->create(['company_id' => $company->id, 'parent_id' => $parent->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/chart-of-accounts/tree');

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
    }
}
