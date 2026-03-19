<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Inventory\Warehouse;
use Tests\TestCase;

class WarehouseTest extends TestCase
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

    private function warehousePayload(string $name = 'Main Warehouse'): array
    {
        return [
            'name'    => $name,
            'address' => '123 Storage Lane',
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_warehouses(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['warehouses.view_any']);

        Warehouse::factory()->create(['company_id' => $company->id, 'name' => 'Visible Warehouse']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/warehouses');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Visible Warehouse']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/warehouses')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/warehouses')
            ->assertForbidden();
    }

    public function test_index_only_returns_warehouses_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userA, $companyA, ['warehouses.view_any']);

        Warehouse::factory()->create(['company_id' => $companyA->id, 'name' => 'WarehouseA']);

        $companyB = Company::factory()->create();
        Warehouse::factory()->create(['company_id' => $companyB->id, 'name' => 'WarehouseB']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/warehouses');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('WarehouseA', $names);
        $this->assertNotContains('WarehouseB', $names);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_warehouse(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['warehouses.create']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/warehouses', $this->warehousePayload('New Warehouse'));

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'New Warehouse']);

        $this->assertDatabaseHas('warehouses', [
            'name'       => 'New Warehouse',
            'company_id' => $company->id,
        ]);
    }

    public function test_store_auto_generates_code_when_not_provided(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['warehouses.create']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/warehouses', $this->warehousePayload());

        $response->assertCreated();
        $code = $response->json('data.code');
        $this->assertNotNull($code);
        $this->assertMatchesRegularExpression('/^WH-\d{5}$/', $code);
    }

    public function test_store_requires_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['warehouses.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/warehouses', ['address' => '123 Lane'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/warehouses', $this->warehousePayload())
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_warehouse(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['warehouses.view']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/warehouses/{$warehouse->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $warehouse->id]);
    }

    public function test_show_returns_404_for_nonexistent_warehouse(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['warehouses.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/warehouses/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/warehouses/{$warehouse->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_warehouse(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['warehouses.update']);

        $warehouse = Warehouse::factory()->create([
            'company_id' => $company->id,
            'name'       => 'Old Name',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/warehouses/{$warehouse->id}", ['name' => 'Updated Name']);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'name' => 'Updated Name']);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/warehouses/{$warehouse->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_warehouse(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['warehouses.delete']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/warehouses/{$warehouse->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/warehouses/{$warehouse->id}")
            ->assertForbidden();
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_warehouse_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['warehouses.view']);
        $this->giveUserPermissions($userB, $companyB, ['warehouses.view']);

        $warehouse = Warehouse::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/warehouses/{$warehouse->id}")
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_update_warehouse_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['warehouses.update']);
        $this->giveUserPermissions($userB, $companyB, ['warehouses.update']);

        $warehouse = Warehouse::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/warehouses/{$warehouse->id}", ['name' => 'Hijacked'])
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_delete_warehouse_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['warehouses.delete']);
        $this->giveUserPermissions($userB, $companyB, ['warehouses.delete']);

        $warehouse = Warehouse::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/warehouses/{$warehouse->id}")
            ->assertNotFound();
    }

    public function test_index_does_not_leak_warehouses_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['warehouses.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['warehouses.view_any']);

        Warehouse::factory()->create(['company_id' => $companyA->id, 'name' => 'WarehouseA']);
        Warehouse::factory()->create(['company_id' => $companyB->id, 'name' => 'WarehouseB']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/warehouses');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('WarehouseA', $names);
        $this->assertNotContains('WarehouseB', $names);
    }
}
