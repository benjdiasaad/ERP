<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Company\Company;
use App\Models\Finance\PaymentTerm;
use App\Models\Sales\Customer;
use App\Models\Sales\DeliveryNote;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesOrder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class SalesOrderPagesTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function createOrder(Company $company, $user, array $permissions = []): SalesOrder
    {
        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Atlas']);

        $this->actingAs($user)->post('/sales/orders', [
            'customer_id' => $customer->id,
            'order_date'  => now()->toDateString(),
            'lines'       => [['description' => 'Écran 27"', 'quantity' => 2, 'unit_price_ht' => 1000, 'tax_rate' => 20]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        return SalesOrder::where('customer_id', $customer->id)->latest('id')->firstOrFail();
    }

    public function test_index_and_create(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['sales_orders.view_any', 'sales_orders.create', 'sales_orders.view']);
        $order = $this->createOrder($company, $user);

        $this->assertEquals('draft', $order->status);
        $this->assertEquals('2400.00', $order->total_ttc);

        $this->actingAs($user)->get('/sales/orders')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Sales/Orders/Index')->has('orders.data', 1)->where('counts.draft.count', 1));
    }

    public function test_full_flow_confirm_deliver_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions([
            'sales_orders.create', 'sales_orders.view', 'sales_orders.confirm',
            'sales_orders.generate_delivery_note', 'sales_orders.generate_invoice',
        ]);
        $order = $this->createOrder($company, $user);
        $term = PaymentTerm::create(['company_id' => $company->id, 'name' => '30 jours', 'days' => 30, 'is_active' => true]);
        $order->update(['payment_term_id' => $term->id]);

        $this->actingAs($user)->post("/sales/orders/{$order->id}/confirm")->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('confirmed', $order->fresh()->status);

        $this->actingAs($user)->post("/sales/orders/{$order->id}/generate-delivery-note")->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(DeliveryNote::where('sales_order_id', $order->id)->exists());
        $this->assertEquals('in_progress', $order->fresh()->status);

        $response = $this->actingAs($user)->post("/sales/orders/{$order->id}/generate-invoice");
        $invoice = Invoice::where('sales_order_id', $order->id)->firstOrFail();
        $response->assertRedirect(route('sales.invoices.show', $invoice));
        $this->assertEquals(now()->addDays(30)->toDateString(), $invoice->due_date->toDateString());

        $this->actingAs($user)->get("/sales/orders/{$order->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Orders/Show')
                ->has('invoices', 1)
                ->has('deliveryNotes', 1)
                ->where('order.data.lines.0.remaining_to_deliver', fn ($value) => (float) $value === 0.0));
    }

    public function test_cannot_edit_confirmed_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['sales_orders.create', 'sales_orders.update', 'sales_orders.confirm']);
        $order = $this->createOrder($company, $user);
        $this->actingAs($user)->post("/sales/orders/{$order->id}/confirm");

        $this->actingAs($user)->put("/sales/orders/{$order->id}", ['notes' => 'x'])->assertSessionHasErrors('status');
    }

    public function test_cancel_with_reason(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['sales_orders.create', 'sales_orders.cancel']);
        $order = $this->createOrder($company, $user);

        $this->actingAs($user)->post("/sales/orders/{$order->id}/cancel", ['reason' => 'Client injoignable'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('cancelled', $order->fresh()->status);
    }

    public function test_create_rejects_foreign_customer(): void
    {
        ['user' => $user] = $this->actingWithPermissions(['sales_orders.create']);
        $foreign = Customer::factory()->create(['name' => 'Foreign']);

        $this->actingAs($user)->post('/sales/orders', [
            'customer_id' => $foreign->id,
            'order_date'  => now()->toDateString(),
            'lines'       => [['description' => 'x', 'quantity' => 1, 'unit_price_ht' => 1]],
        ])->assertSessionHasErrors('customer_id');
    }
}
