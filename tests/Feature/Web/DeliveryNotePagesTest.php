<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Sales\Customer;
use App\Models\Sales\DeliveryNote;
use App\Models\Sales\SalesOrder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class DeliveryNotePagesTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_note_generated_from_order_goes_through_the_full_workflow(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions([
            'sales_orders.create', 'sales_orders.confirm', 'sales_orders.generate_delivery_note',
            'delivery_notes.view', 'delivery_notes.update', 'delivery_notes.ship', 'delivery_notes.deliver',
        ]);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Atlas']);
        $this->actingAs($user)->post('/sales/orders', [
            'customer_id' => $customer->id,
            'order_date'  => now()->toDateString(),
            'lines'       => [['description' => 'Chaise', 'quantity' => 4, 'unit_price_ht' => 500, 'tax_rate' => 20]],
        ]);
        $order = SalesOrder::firstOrFail();
        $this->actingAs($user)->post("/sales/orders/{$order->id}/confirm");
        $this->actingAs($user)->post("/sales/orders/{$order->id}/generate-delivery-note")->assertRedirect()->assertSessionHasNoErrors();

        $note = DeliveryNote::with('lines')->firstOrFail();
        $this->assertEquals('4.00', $note->lines->first()->shipped_quantity, 'generated lines carry the quantity to ship');

        $this->actingAs($user)->post("/sales/delivery-notes/{$note->id}/ready")->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/sales/delivery-notes/{$note->id}/ship", ['carrier' => 'CTM', 'tracking_number' => 'TRK-1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($user)->post("/sales/delivery-notes/{$note->id}/deliver")->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('delivered', $note->fresh()->status);
        $this->assertEquals('CTM', $note->fresh()->carrier);
        $this->assertEquals('delivered', $order->fresh()->status);
        $this->assertEquals('4.0000', $order->lines()->first()->delivered_quantity, 'delivered quantity is not counted twice');

        $this->actingAs($user)->get("/sales/delivery-notes/{$note->id}")
            ->assertInertia(fn (Assert $page) => $page->component('Sales/DeliveryNotes/Show')->where('note.data.status', 'delivered'));
    }

    public function test_cannot_ship_a_draft(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['delivery_notes.create', 'delivery_notes.ship']);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Atlas']);
        $this->actingAs($user)->post('/sales/delivery-notes', [
            'customer_id' => $customer->id,
            'lines'       => [['description' => 'Échantillon', 'ordered_quantity' => 1, 'shipped_quantity' => 1]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $note = DeliveryNote::firstOrFail();

        $this->assertNull($note->sales_order_id, 'standalone delivery notes are allowed');
        $this->actingAs($user)->post("/sales/delivery-notes/{$note->id}/ship")->assertSessionHasErrors('status');
    }

    public function test_index_counts_and_isolation(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['delivery_notes.view_any', 'delivery_notes.view']);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Atlas']);
        DeliveryNote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id, 'status' => 'draft']);
        $foreign = DeliveryNote::factory()->create();

        $this->actingAs($user)->get('/sales/delivery-notes')
            ->assertInertia(fn (Assert $page) => $page->component('Sales/DeliveryNotes/Index')->has('notes.data', 1)->where('counts.draft', 1));

        $this->actingAs($user)->get("/sales/delivery-notes/{$foreign->id}")->assertNotFound();
    }
}
