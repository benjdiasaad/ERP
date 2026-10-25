<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\StockInventory;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class StockPagesTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function allPermissions(): array
    {
        return [
            ...$this->modulePermissions('products'),
            ...$this->modulePermissions('product_categories'),
            ...$this->modulePermissions('warehouses'),
            'stock_levels.view_any',
            ...$this->modulePermissions('stock_movements', ['transfer']),
            ...$this->modulePermissions('stock_inventories', ['start', 'validate', 'cancel']),
        ];
    }

    private function onHand(Product $product, Warehouse $warehouse): float
    {
        return (float) StockLevel::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity_on_hand');
    }

    public function test_index_pages_render_and_are_isolated(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $product = Product::factory()->create(['company_id' => $company->id]);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $foreignProduct = Product::factory()->create();
        $foreignWarehouse = Warehouse::factory()->create();

        $pages = [
            '/stock/products'    => 'Inventory/Products/Index',
            '/stock/categories'  => 'Inventory/Categories/Index',
            '/stock/warehouses'  => 'Inventory/Warehouses/Index',
            '/stock/levels'      => 'Inventory/Levels/Index',
            '/stock/movements'   => 'Inventory/Movements/Index',
            '/stock/inventories' => 'Inventory/Inventories/Index',
        ];
        foreach ($pages as $url => $component) {
            $this->actingAs($user)->get($url)->assertInertia(fn (Assert $page) => $page->component($component));
        }

        $this->actingAs($user)->get('/stock/products')->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
        $this->actingAs($user)->get("/stock/products/{$product->id}")->assertInertia(fn (Assert $page) => $page->component('Inventory/Products/Show'));
        $this->actingAs($user)->get("/stock/warehouses/{$warehouse->id}")->assertInertia(fn (Assert $page) => $page->component('Inventory/Warehouses/Show'));
        $this->actingAs($user)->get("/stock/products/{$foreignProduct->id}")->assertNotFound();
        $this->actingAs($user)->get("/stock/warehouses/{$foreignWarehouse->id}")->assertNotFound();
    }

    public function test_pages_require_permissions(): void
    {
        ['user' => $user] = $this->actingWithPermissions(['products.view_any']);

        $this->actingAs($user)->get('/stock/products')->assertOk();
        $this->actingAs($user)->get('/stock/warehouses')->assertForbidden();
        $this->actingAs($user)->get('/stock/movements')->assertForbidden();
    }

    public function test_product_is_created_with_a_generated_code_and_a_company_category(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $category = ProductCategory::factory()->create(['company_id' => $company->id]);
        $foreignCategory = ProductCategory::factory()->create();

        $this->actingAs($user)->post('/stock/products', ['name' => 'Chaise', 'category_id' => $foreignCategory->id])
            ->assertSessionHasErrors('category_id');

        $this->actingAs($user)->post('/stock/products', [
            'name' => 'Chaise', 'category_id' => $category->id, 'type' => 'product', 'sale_price' => 500, 'is_stockable' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $product = Product::where('name', 'Chaise')->firstOrFail();
        $this->assertMatchesRegularExpression('/^PROD-\d{4}-00001$/', $product->code);
        $this->assertEquals($company->id, $product->company_id);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $category = ProductCategory::factory()->create(['company_id' => $company->id]);
        Product::factory()->create(['company_id' => $company->id, 'category_id' => $category->id]);

        $this->actingAs($user)->delete("/stock/categories/{$category->id}")->assertSessionHasErrors('category');
        $this->actingAs($user)->put("/stock/categories/{$category->id}", ['name' => $category->name, 'parent_id' => $category->id])
            ->assertSessionHasErrors('parent_id');
        $this->assertNotSoftDeleted($category);
    }

    public function test_only_one_default_warehouse(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $first = Warehouse::factory()->create(['company_id' => $company->id, 'is_default' => true]);

        $this->actingAs($user)->post('/stock/warehouses', ['name' => 'Dépôt Tanger', 'is_default' => true, 'is_active' => true])
            ->assertRedirect()->assertSessionHasNoErrors();

        $second = Warehouse::where('name', 'Dépôt Tanger')->firstOrFail();
        $this->assertTrue($second->is_default);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertStringStartsWith('WH-', $second->code);
    }

    public function test_manual_movements_and_transfer_update_stock(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $product = Product::factory()->create(['company_id' => $company->id, 'is_stockable' => true]);
        $casa = Warehouse::factory()->create(['company_id' => $company->id]);
        $rabat = Warehouse::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user)->post('/stock/movements', ['type' => 'in', 'product_id' => $product->id, 'warehouse_id' => $casa->id, 'quantity' => 10])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/stock/movements', ['type' => 'out', 'product_id' => $product->id, 'warehouse_id' => $casa->id, 'quantity' => 50])
            ->assertSessionHasErrors('quantity');
        $this->actingAs($user)->post('/stock/movements/transfer', [
            'product_id' => $product->id, 'from_warehouse_id' => $casa->id, 'to_warehouse_id' => $rabat->id, 'quantity' => 4,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(6.0, $this->onHand($product, $casa));
        $this->assertEquals(4.0, $this->onHand($product, $rabat));
        $this->assertEquals(3, StockMovement::count());

        $this->actingAs($user)->get('/stock/levels?low=1')->assertInertia(fn (Assert $page) => $page->has('levels.data', 0));
        $this->actingAs($user)->get("/stock/movements?warehouse_id={$rabat->id}")->assertInertia(fn (Assert $page) => $page->has('movements.data', 1));
    }

    public function test_inventory_count_adjusts_stock_on_validation(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $chair = Product::factory()->create(['company_id' => $company->id, 'is_stockable' => true, 'is_active' => true, 'name' => 'Chaise']);
        $table = Product::factory()->create(['company_id' => $company->id, 'is_stockable' => true, 'is_active' => true, 'name' => 'Table']);
        Product::factory()->service()->create(['company_id' => $company->id]);
        foreach ([[$chair, 10], [$table, 3]] as [$product, $quantity]) {
            $this->actingAs($user)->post('/stock/movements', ['type' => 'in', 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => $quantity]);
        }

        $this->actingAs($user)->post('/stock/inventories', ['warehouse_id' => $warehouse->id, 'all_products' => true])
            ->assertRedirect()->assertSessionHasNoErrors();
        $inventory = StockInventory::with('lines')->firstOrFail();
        $this->assertNotEmpty($inventory->reference);
        $this->assertCount(2, $inventory->lines, 'services are not counted');

        $this->actingAs($user)->post("/stock/inventories/{$inventory->id}/start")->assertRedirect()->assertSessionHasNoErrors();
        $lines = $inventory->lines->keyBy('product_id');
        $this->actingAs($user)->put("/stock/inventories/{$inventory->id}", ['lines' => [
            ['id' => $lines[$chair->id]->id, 'counted_quantity' => 8, 'notes' => '2 cassées'],
            ['id' => $lines[$table->id]->id, 'counted_quantity' => 0],
        ]])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/stock/inventories/{$inventory->id}/validate")->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('validated', $inventory->fresh()->status);
        $this->assertEquals(8.0, $this->onHand($chair, $warehouse));
        $this->assertEquals(0.0, $this->onHand($table, $warehouse), 'a product counted at zero is emptied');

        $this->actingAs($user)->get("/stock/inventories/{$inventory->id}")
            ->assertInertia(fn (Assert $page) => $page->component('Inventory/Inventories/Show')->where('inventory.data.status', 'validated')->has('inventory.data.lines', 2));
    }

    public function test_counts_cannot_be_entered_before_starting(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $product = Product::factory()->create(['company_id' => $company->id, 'is_stockable' => true]);

        $this->actingAs($user)->post('/stock/inventories', ['warehouse_id' => $warehouse->id, 'lines' => [['product_id' => $product->id]]]);
        $inventory = StockInventory::firstOrFail();

        $this->actingAs($user)->post("/stock/inventories/{$inventory->id}/validate")->assertSessionHasErrors('status');
        $this->actingAs($user)->post("/stock/inventories/{$inventory->id}/cancel", ['reason' => 'Erreur'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('cancelled', $inventory->fresh()->status);
    }
}
