<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockInventory;
use App\Models\Inventory\StockInventoryLine;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use Tests\TestCase;

class StockInventoryTest extends TestCase
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

    private function createInventoryPayload(int $warehouseId, array $lines = []): array
    {
        return [
            'warehouse_id' => $warehouseId,
            'reference'    => 'INV-' . uniqid(),
            'lines'        => $lines,
            'notes'        => 'Physical inventory count',
        ];
    }

    // ─── Create Stock Inventory ───────────────────────────────────────────────

    public function test_user_can_create_stock_inventory(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_inventories.create']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-inventories', $this->createInventoryPayload($warehouse->id));

        $response->assertCreated()
            ->assertJsonFragment(['status' => 'draft']);

        $this->assertDatabaseHas('stock_inventories', [
            'warehouse_id' => $warehouse->id,
            'company_id'   => $company->id,
            'status'       => 'draft',
        ]);
    }

    public function test_create_stock_inventory_with_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_inventories.create']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $product = Product::factory()->create(['company_id' => $company->id]);

        $payload = $this->createInventoryPayload($warehouse->id, [
            [
                'product_id'       => $product->id,
                'counted_quantity' => 50,
            ],
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-inventories', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('stock_inventory_lines', [
            'product_id'       => $product->id,
            'counted_quantity' => '50.0000',
        ]);
    }

    // ─── Count → In Progress ──────────────────────────────────────────────────

    public function test_inventory_can_transition_from_draft_to_in_progress(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_inventories.start']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $product = Product::factory()->create(['company_id' => $company->id]);

        $inventory = StockInventory::factory()->create([
            'company_id'   => $company->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'draft',
        ]);

        StockInventoryLine::factory()->create([
            'stock_inventory_id' => $inventory->id,
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouse->id,
            'theoretical_quantity' => 100,
            'counted_quantity'   => 100,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/stock-inventories/{$inventory->id}/start");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'in_progress']);

        $this->assertDatabaseHas('stock_inventories', [
            'id'     => $inventory->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_inventory_cannot_start_without_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_inventories.start']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $inventory = StockInventory::factory()->create([
            'company_id'   => $company->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'draft',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/stock-inventories/{$inventory->id}/start");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    // ─── Validate → Adjustment Movements ──────────────────────────────────────

    public function test_validate_inventory_generates_adjustment_movements_for_variance(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_inventories.validate']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $product = Product::factory()->create(['company_id' => $company->id]);

        // Create initial stock level
        StockLevel::create([
            'company_id'         => $company->id,
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouse->id,
            'quantity_on_hand'   => 100,
            'quantity_reserved'  => 0,
        ]);

        $inventory = StockInventory::factory()->create([
            'company_id'   => $company->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'in_progress',
        ]);

        // Create line with variance: theoretical 100, counted 85 (variance -15)
        StockInventoryLine::factory()->create([
            'stock_inventory_id'   => $inventory->id,
            'product_id'           => $product->id,
            'warehouse_id'         => $warehouse->id,
            'theoretical_quantity' => 100,
            'counted_quantity'     => 85,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/stock-inventories/{$inventory->id}/validate");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'validated']);

        // Check that adjustment movement was created
        $movement = StockMovement::where('product_id', $product->id)
            ->where('type', 'adjustment')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals('85.0000', $movement->quantity);
        $this->assertEquals(StockInventory::class, $movement->source_type);
        $this->assertEquals($inventory->id, $movement->source_id);
    }

    public function test_validate_inventory_updates_stock_level_to_counted_quantity(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_inventories.validate']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $product = Product::factory()->create(['company_id' => $company->id]);

        // Create initial stock level
        $stockLevel = StockLevel::create([
            'company_id'         => $company->id,
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouse->id,
            'quantity_on_hand'   => 100,
            'quantity_reserved'  => 0,
        ]);

        $inventory = StockInventory::factory()->create([
            'company_id'   => $company->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'in_progress',
        ]);

        StockInventoryLine::factory()->create([
            'stock_inventory_id'   => $inventory->id,
            'product_id'           => $product->id,
            'warehouse_id'         => $warehouse->id,
            'theoretical_quantity' => 100,
            'counted_quantity'     => 95,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/stock-inventories/{$inventory->id}/validate");

        $stockLevel->refresh();
        $this->assertEquals('95.0000', $stockLevel->quantity_on_hand);
    }

    public function test_validate_inventory_does_not_create_movement_for_no_variance(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_inventories.validate']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $product = Product::factory()->create(['company_id' => $company->id]);

        StockLevel::create([
            'company_id'         => $company->id,
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouse->id,
            'quantity_on_hand'   => 100,
            'quantity_reserved'  => 0,
        ]);

        $inventory = StockInventory::factory()->create([
            'company_id'   => $company->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'in_progress',
        ]);

        // No variance: theoretical 100, counted 100
        StockInventoryLine::factory()->create([
            'stock_inventory_id'   => $inventory->id,
            'product_id'           => $product->id,
            'warehouse_id'         => $warehouse->id,
            'theoretical_quantity' => 100,
            'counted_quantity'     => 100,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/stock-inventories/{$inventory->id}/validate");

        $movements = StockMovement::where('product_id', $product->id)
            ->where('type', 'adjustment')
            ->get();

        $this->assertCount(0, $movements);
    }

    public function test_validate_inventory_cannot_be_called_on_draft(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_inventories.validate']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $inventory = StockInventory::factory()->create([
            'company_id'   => $company->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'draft',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/stock-inventories/{$inventory->id}/validate");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // ─── Multiple Products with Variance ──────────────────────────────────────

    public function test_validate_inventory_with_multiple_products_and_variances(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_inventories.validate']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $product1 = Product::factory()->create(['company_id' => $company->id]);
        $product2 = Product::factory()->create(['company_id' => $company->id]);

        // Create initial stock levels
        StockLevel::create([
            'company_id'         => $company->id,
            'product_id'         => $product1->id,
            'warehouse_id'       => $warehouse->id,
            'quantity_on_hand'   => 100,
            'quantity_reserved'  => 0,
        ]);

        StockLevel::create([
            'company_id'         => $company->id,
            'product_id'         => $product2->id,
            'warehouse_id'       => $warehouse->id,
            'quantity_on_hand'   => 50,
            'quantity_reserved'  => 0,
        ]);

        $inventory = StockInventory::factory()->create([
            'company_id'   => $company->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'in_progress',
        ]);

        // Product 1: variance of -10
        StockInventoryLine::factory()->create([
            'stock_inventory_id'   => $inventory->id,
            'product_id'           => $product1->id,
            'warehouse_id'         => $warehouse->id,
            'theoretical_quantity' => 100,
            'counted_quantity'     => 90,
        ]);

        // Product 2: variance of +5
        StockInventoryLine::factory()->create([
            'stock_inventory_id'   => $inventory->id,
            'product_id'           => $product2->id,
            'warehouse_id'         => $warehouse->id,
            'theoretical_quantity' => 50,
            'counted_quantity'     => 55,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/stock-inventories/{$inventory->id}/validate");

        // Check both adjustment movements were created
        $movements = StockMovement::where('type', 'adjustment')
            ->whereIn('product_id', [$product1->id, $product2->id])
            ->get();

        $this->assertCount(2, $movements);

        // Verify stock levels were updated
        $this->assertEquals('90.0000', StockLevel::where('product_id', $product1->id)->first()->quantity_on_hand);
        $this->assertEquals('55.0000', StockLevel::where('product_id', $product2->id)->first()->quantity_on_hand);
    }

    // ─── Update Draft Inventory ───────────────────────────────────────────────

    public function test_can_update_draft_inventory(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_inventories.update']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $inventory = StockInventory::factory()->create([
            'company_id'   => $company->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'draft',
            'notes'        => 'Old notes',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/stock-inventories/{$inventory->id}", ['notes' => 'Updated notes']);

        $response->assertOk()
            ->assertJsonFragment(['notes' => 'Updated notes']);
    }

    public function test_cannot_update_in_progress_inventory(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_inventories.update']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $inventory = StockInventory::factory()->create([
            'company_id'   => $company->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'in_progress',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/stock-inventories/{$inventory->id}", ['notes' => 'Updated']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // ─── Delete Draft Inventory ───────────────────────────────────────────────

    public function test_can_delete_draft_inventory(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_inventories.delete']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $inventory = StockInventory::factory()->create([
            'company_id'   => $company->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'draft',
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/stock-inventories/{$inventory->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('stock_inventories', ['id' => $inventory->id]);
    }

    public function test_cannot_delete_in_progress_inventory(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_inventories.delete']);

        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        $inventory = StockInventory::factory()->create([
            'company_id'   => $company->id,
            'warehouse_id' => $warehouse->id,
            'status'       => 'in_progress',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/stock-inventories/{$inventory->id}");

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_stock_inventories_are_isolated_by_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['stock_inventories.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['stock_inventories.view_any']);

        $warehouseA = Warehouse::factory()->create(['company_id' => $companyA->id]);
        $warehouseB = Warehouse::factory()->create(['company_id' => $companyB->id]);

        StockInventory::factory()->create([
            'company_id'   => $companyA->id,
            'warehouse_id' => $warehouseA->id,
            'reference'    => 'INV-A-001',
        ]);

        StockInventory::factory()->create([
            'company_id'   => $companyB->id,
            'warehouse_id' => $warehouseB->id,
            'reference'    => 'INV-B-001',
        ]);

        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/stock-inventories');

        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson('/api/stock-inventories');

        $refsA = collect($responseA->json('data'))->pluck('reference')->toArray();
        $refsB = collect($responseB->json('data'))->pluck('reference')->toArray();

        $this->assertContains('INV-A-001', $refsA);
        $this->assertNotContains('INV-B-001', $refsA);

        $this->assertContains('INV-B-001', $refsB);
        $this->assertNotContains('INV-A-001', $refsB);
    }

    public function test_user_from_company_b_cannot_view_inventory_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['stock_inventories.view']);
        $this->giveUserPermissions($userB, $companyB, ['stock_inventories.view']);

        $warehouseA = Warehouse::factory()->create(['company_id' => $companyA->id]);

        $inventory = StockInventory::factory()->create([
            'company_id'   => $companyA->id,
            'warehouse_id' => $warehouseA->id,
        ]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/stock-inventories/{$inventory->id}")
            ->assertNotFound();
    }
}
