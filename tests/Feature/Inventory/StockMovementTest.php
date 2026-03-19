<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use Tests\TestCase;

class StockMovementTest extends TestCase
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

    private function createProductAndWarehouse(Company $company): array
    {
        $product = Product::factory()->create(['company_id' => $company->id]);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

        return ['product' => $product, 'warehouse' => $warehouse];
    }

    // ─── Stock In Movement ────────────────────────────────────────────────────

    public function test_stock_in_movement_increases_quantity_on_hand(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_movements.create']);

        ['product' => $product, 'warehouse' => $warehouse] = $this->createProductAndWarehouse($company);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-movements', [
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
                'type'         => 'in',
                'quantity'     => 100,
                'reference'    => 'REC-001',
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['type' => 'in', 'quantity' => '100.0000']);

        $stockLevel = StockLevel::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        $this->assertNotNull($stockLevel);
        $this->assertEquals('100.0000', $stockLevel->quantity_on_hand);
    }

    public function test_stock_in_movement_creates_stock_level_if_not_exists(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_movements.create']);

        ['product' => $product, 'warehouse' => $warehouse] = $this->createProductAndWarehouse($company);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-movements', [
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
                'type'         => 'in',
                'quantity'     => 50,
            ]);

        $this->assertDatabaseHas('stock_levels', [
            'product_id'   => $product->id,
            'warehouse_id' => $warehouse->id,
            'company_id'   => $company->id,
        ]);
    }

    // ─── Stock Out Movement ───────────────────────────────────────────────────

    public function test_stock_out_movement_decreases_quantity_on_hand(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_movements.create']);

        ['product' => $product, 'warehouse' => $warehouse] = $this->createProductAndWarehouse($company);

        // Create initial stock
        StockLevel::create([
            'company_id'         => $company->id,
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouse->id,
            'quantity_on_hand'   => 100,
            'quantity_reserved'  => 0,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-movements', [
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
                'type'         => 'out',
                'quantity'     => 30,
                'reference'    => 'DEL-001',
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['type' => 'out', 'quantity' => '30.0000']);

        $stockLevel = StockLevel::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        $this->assertEquals('70.0000', $stockLevel->quantity_on_hand);
    }

    public function test_stock_out_movement_fails_if_insufficient_stock(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_movements.create']);

        ['product' => $product, 'warehouse' => $warehouse] = $this->createProductAndWarehouse($company);

        // Create stock with only 20 units
        StockLevel::create([
            'company_id'         => $company->id,
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouse->id,
            'quantity_on_hand'   => 20,
            'quantity_reserved'  => 0,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-movements', [
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
                'type'         => 'out',
                'quantity'     => 50,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    // ─── Stock Transfer Movement ──────────────────────────────────────────────

    public function test_stock_transfer_moves_stock_between_warehouses(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_movements.transfer']);

        ['product' => $product, 'warehouse' => $warehouse] = $this->createProductAndWarehouse($company);
        $warehouse2 = Warehouse::factory()->create(['company_id' => $company->id]);

        // Create initial stock in first warehouse
        StockLevel::create([
            'company_id'         => $company->id,
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouse->id,
            'quantity_on_hand'   => 100,
            'quantity_reserved'  => 0,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-movements/transfer', [
                'product_id'         => $product->id,
                'from_warehouse_id'  => $warehouse->id,
                'to_warehouse_id'    => $warehouse2->id,
                'quantity'           => 40,
                'reference'          => 'TRANSFER-001',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['out_movement', 'in_movement']);

        // Check source warehouse decreased
        $sourceLevel = StockLevel::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        $this->assertEquals('60.0000', $sourceLevel->quantity_on_hand);

        // Check destination warehouse increased
        $destLevel = StockLevel::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse2->id)
            ->first();
        $this->assertEquals('40.0000', $destLevel->quantity_on_hand);
    }

    public function test_stock_transfer_creates_two_movements(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_movements.transfer']);

        ['product' => $product, 'warehouse' => $warehouse] = $this->createProductAndWarehouse($company);
        $warehouse2 = Warehouse::factory()->create(['company_id' => $company->id]);

        StockLevel::create([
            'company_id'         => $company->id,
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouse->id,
            'quantity_on_hand'   => 100,
            'quantity_reserved'  => 0,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-movements/transfer', [
                'product_id'         => $product->id,
                'from_warehouse_id'  => $warehouse->id,
                'to_warehouse_id'    => $warehouse2->id,
                'quantity'           => 25,
            ]);

        // Should have 2 movements: one out, one in
        $movements = StockMovement::where('product_id', $product->id)
            ->where('type', 'transfer')
            ->get();

        $this->assertCount(2, $movements);
        $this->assertTrue($movements->contains(fn($m) => (float) $m->quantity === -25.0));
        $this->assertTrue($movements->contains(fn($m) => (float) $m->quantity === 25.0));
    }

    public function test_stock_transfer_fails_if_insufficient_stock(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_movements.transfer']);

        ['product' => $product, 'warehouse' => $warehouse] = $this->createProductAndWarehouse($company);
        $warehouse2 = Warehouse::factory()->create(['company_id' => $company->id]);

        StockLevel::create([
            'company_id'         => $company->id,
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouse->id,
            'quantity_on_hand'   => 10,
            'quantity_reserved'  => 0,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-movements/transfer', [
                'product_id'         => $product->id,
                'from_warehouse_id'  => $warehouse->id,
                'to_warehouse_id'    => $warehouse2->id,
                'quantity'           => 50,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    // ─── Stock Adjustment Movement ────────────────────────────────────────────

    public function test_stock_adjustment_sets_exact_quantity(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_movements.create']);

        ['product' => $product, 'warehouse' => $warehouse] = $this->createProductAndWarehouse($company);

        StockLevel::create([
            'company_id'         => $company->id,
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouse->id,
            'quantity_on_hand'   => 100,
            'quantity_reserved'  => 0,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-movements', [
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
                'type'         => 'adjustment',
                'quantity'     => 75,
                'reference'    => 'ADJ-001',
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['type' => 'adjustment']);

        $stockLevel = StockLevel::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        $this->assertEquals('75.0000', $stockLevel->quantity_on_hand);
    }

    // ─── Stock Return Movement ────────────────────────────────────────────────

    public function test_stock_return_movement_increases_quantity(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_movements.create']);

        ['product' => $product, 'warehouse' => $warehouse] = $this->createProductAndWarehouse($company);

        StockLevel::create([
            'company_id'         => $company->id,
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouse->id,
            'quantity_on_hand'   => 50,
            'quantity_reserved'  => 0,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-movements', [
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
                'type'         => 'return',
                'quantity'     => 20,
                'reference'    => 'RET-001',
            ]);

        $response->assertCreated()
            ->assertJsonFragment(['type' => 'return']);

        $stockLevel = StockLevel::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        $this->assertEquals('70.0000', $stockLevel->quantity_on_hand);
    }

    // ─── Validation ────────────────────────────────────────────────────────────

    public function test_stock_movement_requires_positive_quantity(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_movements.create']);

        ['product' => $product, 'warehouse' => $warehouse] = $this->createProductAndWarehouse($company);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-movements', [
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
                'type'         => 'in',
                'quantity'     => 0,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_stock_movement_requires_valid_type(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_movements.create']);

        ['product' => $product, 'warehouse' => $warehouse] = $this->createProductAndWarehouse($company);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-movements', [
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
                'type'         => 'invalid_type',
                'quantity'     => 10,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    // ─── Polymorphic Source Tracking ──────────────────────────────────────────

    public function test_stock_movement_tracks_polymorphic_source(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['stock_movements.create']);

        ['product' => $product, 'warehouse' => $warehouse] = $this->createProductAndWarehouse($company);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/stock-movements', [
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
                'type'         => 'in',
                'quantity'     => 50,
                'source_type'  => 'App\\Models\\Purchasing\\ReceptionNote',
                'source_id'    => 123,
            ]);

        $response->assertCreated();

        $movement = StockMovement::where('product_id', $product->id)->first();
        $this->assertEquals('App\\Models\\Purchasing\\ReceptionNote', $movement->source_type);
        $this->assertEquals(123, $movement->source_id);
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_stock_movements_are_isolated_by_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['stock_movements.create']);
        $this->giveUserPermissions($userB, $companyB, ['stock_movements.create']);

        $productA = Product::factory()->create(['company_id' => $companyA->id]);
        $warehouseA = Warehouse::factory()->create(['company_id' => $companyA->id]);

        $productB = Product::factory()->create(['company_id' => $companyB->id]);
        $warehouseB = Warehouse::factory()->create(['company_id' => $companyB->id]);

        $this->withHeaders($this->authHeaders($userA))
            ->postJson('/api/stock-movements', [
                'product_id'   => $productA->id,
                'warehouse_id' => $warehouseA->id,
                'type'         => 'in',
                'quantity'     => 100,
            ]);

        $this->withHeaders($this->authHeaders($userB))
            ->postJson('/api/stock-movements', [
                'product_id'   => $productB->id,
                'warehouse_id' => $warehouseB->id,
                'type'         => 'in',
                'quantity'     => 200,
            ]);

        $movementsA = StockMovement::where('company_id', $companyA->id)->get();
        $movementsB = StockMovement::where('company_id', $companyB->id)->get();

        $this->assertCount(1, $movementsA);
        $this->assertCount(1, $movementsB);
        $this->assertEquals(100, (float) $movementsA->first()->quantity);
        $this->assertEquals(200, (float) $movementsB->first()->quantity);
    }
}
