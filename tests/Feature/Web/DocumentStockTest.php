<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\ReceptionNote;
use App\Models\Purchasing\ReceptionNoteLine;
use App\Models\Purchasing\Supplier;
use App\Models\Sales\Customer;
use App\Models\Sales\DeliveryNote;
use App\Models\Sales\DeliveryNoteLine;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

/**
 * Receptions put goods into stock; deliveries take them out (and returns put them back).
 */
class DocumentStockTest extends TestCase
{
    use InteractsWithCrm;

    private function onHand(Product $product, Warehouse $warehouse): float
    {
        return (float) StockLevel::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->value('quantity_on_hand');
    }

    public function test_reception_then_delivery_then_return_moves_stock(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions([
            'reception_notes.confirm', 'delivery_notes.ship', 'delivery_notes.deliver', 'delivery_notes.return',
        ]);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id, 'is_default' => true]);
        $product = Product::factory()->create(['company_id' => $company->id, 'is_stockable' => true]);
        $service = Product::factory()->service()->create(['company_id' => $company->id]);

        $reception = ReceptionNote::factory()->draft()->create([
            'company_id' => $company->id, 'supplier_id' => Supplier::factory()->create(['company_id' => $company->id])->id,
            'warehouse_id' => $warehouse->id,
        ]);
        foreach ([$product, $service] as $item) {
            ReceptionNoteLine::create([
                'company_id' => $company->id, 'reception_note_id' => $reception->id, 'product_id' => $item->id,
                'description' => $item->name, 'ordered_quantity' => 10, 'received_quantity' => 10, 'rejected_quantity' => 0,
            ]);
        }
        $this->actingAs($user)->post("/purchasing/receptions/{$reception->id}/confirm")->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(10.0, $this->onHand($product, $warehouse));
        $this->assertEquals(0.0, $this->onHand($service, $warehouse), 'services are not stocked');

        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $delivery = DeliveryNote::factory()->ready()->create([
            'company_id' => $company->id, 'customer_id' => $customer->id, 'warehouse_id' => $warehouse->id, 'sales_order_id' => null,
        ]);
        DeliveryNoteLine::create([
            'delivery_note_id' => $delivery->id, 'product_id' => $product->id, 'description' => $product->name,
            'ordered_quantity' => 4, 'shipped_quantity' => 4,
        ]);
        $this->actingAs($user)->post("/sales/delivery-notes/{$delivery->id}/ship")->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(6.0, $this->onHand($product, $warehouse));

        $this->actingAs($user)->post("/sales/delivery-notes/{$delivery->id}/deliver")->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/sales/delivery-notes/{$delivery->id}/return", ['reason' => 'Abîmé'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(10.0, $this->onHand($product, $warehouse));
    }

    public function test_shipping_more_than_available_is_refused(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['delivery_notes.ship']);
        $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
        $product = Product::factory()->create(['company_id' => $company->id, 'is_stockable' => true]);
        $delivery = DeliveryNote::factory()->ready()->create([
            'company_id' => $company->id, 'customer_id' => Customer::factory()->create(['company_id' => $company->id])->id,
            'warehouse_id' => $warehouse->id, 'sales_order_id' => null,
        ]);
        DeliveryNoteLine::create([
            'delivery_note_id' => $delivery->id, 'product_id' => $product->id, 'description' => $product->name,
            'ordered_quantity' => 3, 'shipped_quantity' => 3,
        ]);

        $this->actingAs($user)->post("/sales/delivery-notes/{$delivery->id}/ship")->assertSessionHasErrors('quantity');
        $this->assertEquals('ready', $delivery->fresh()->status, 'nothing is shipped when stock is short');
    }

    public function test_notes_without_a_warehouse_do_not_move_stock(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['delivery_notes.ship']);
        $product = Product::factory()->create(['company_id' => $company->id, 'is_stockable' => true]);
        $delivery = DeliveryNote::factory()->ready()->create([
            'company_id' => $company->id, 'customer_id' => Customer::factory()->create(['company_id' => $company->id])->id,
            'warehouse_id' => null, 'sales_order_id' => null,
        ]);
        DeliveryNoteLine::create([
            'delivery_note_id' => $delivery->id, 'product_id' => $product->id, 'description' => $product->name,
            'ordered_quantity' => 3, 'shipped_quantity' => 3,
        ]);

        $this->actingAs($user)->post("/sales/delivery-notes/{$delivery->id}/ship")->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(0, StockLevel::count());
    }
}
