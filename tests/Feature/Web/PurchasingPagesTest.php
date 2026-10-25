<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Finance\PaymentTerm;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseRequest;
use App\Models\Purchasing\ReceptionNote;
use App\Models\Purchasing\Supplier;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class PurchasingPagesTest extends TestCase
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
            ...$this->modulePermissions('suppliers'),
            ...$this->modulePermissions('purchase_requests', ['submit', 'approve', 'reject', 'cancel', 'convert']),
            ...$this->modulePermissions('purchase_orders', ['send', 'confirm', 'cancel', 'generate_reception', 'generate_invoice']),
            ...$this->modulePermissions('reception_notes', ['confirm', 'cancel']),
            ...$this->modulePermissions('purchase_invoices', ['send', 'cancel', 'record_payment']),
        ];
    }

    public function test_full_purchase_cycle_from_request_to_paid_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        $this->actingAs($user);

        // Supplier with an empty credit limit (form sends null).
        $this->post('/purchasing/suppliers', ['type' => 'company', 'name' => 'Maroc Bureautique', 'credit_limit' => null, 'is_active' => true])
            ->assertRedirect()->assertSessionHasNoErrors();
        $supplier = Supplier::firstOrFail();
        $supplier->update(['payment_term_id' => PaymentTerm::create(['company_id' => $company->id, 'name' => '60 jours', 'days' => 60, 'is_active' => true])->id]);

        // Request without supplier → submit → approve → convert (supplier chosen at conversion).
        $this->post('/purchasing/requests', [
            'title' => 'Postes comptabilité',
            'lines' => [['description' => 'Ordinateur portable', 'quantity' => 2, 'estimated_unit_price' => 6500]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $request = PurchaseRequest::firstOrFail();
        $this->post("/purchasing/requests/{$request->id}/submit")->assertRedirect()->assertSessionHasNoErrors();
        $this->post("/purchasing/requests/{$request->id}/approve")->assertRedirect()->assertSessionHasNoErrors();
        $this->post("/purchasing/requests/{$request->id}/convert")->assertSessionHasErrors('supplier_id');
        $this->post("/purchasing/requests/{$request->id}/convert", ['supplier_id' => $supplier->id])->assertRedirect()->assertSessionHasNoErrors();

        $order = PurchaseOrder::with('lines')->firstOrFail();
        $this->assertEquals('converted', $request->fresh()->status);
        $this->assertEquals('6500.00', $order->lines->first()->unit_price_ht, 'estimated price carried to the order');
        $this->assertEquals('15600.00', $order->total_ttc, '2 × 6 500 + 20 % VAT');

        // Order: send → confirm → reception (partial, then confirm).
        $order->update(['payment_term_id' => $supplier->payment_term_id]);
        $this->post("/purchasing/orders/{$order->id}/send")->assertRedirect()->assertSessionHasNoErrors();
        $this->post("/purchasing/orders/{$order->id}/confirm")->assertRedirect()->assertSessionHasNoErrors();
        $this->post("/purchasing/orders/{$order->id}/generate-reception")->assertRedirect()->assertSessionHasNoErrors();

        $note = ReceptionNote::with('lines')->firstOrFail();
        $line = $note->lines->first();
        $this->put("/purchasing/receptions/{$note->id}", ['lines' => [[
            'id' => $line->id, 'purchase_order_line_id' => $line->purchase_order_line_id, 'description' => $line->description,
            'ordered_quantity' => 2, 'received_quantity' => 1, 'rejected_quantity' => 1,
        ]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->post("/purchasing/receptions/{$note->id}/confirm")->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('in_progress', $order->fresh()->status, 'partly received');

        // Invoice generated from the order follows the 60-day terms.
        $response = $this->post("/purchasing/orders/{$order->id}/generate-invoice");
        $invoice = PurchaseInvoice::firstOrFail();
        $response->assertRedirect(route('purchasing.invoices.show', $invoice));
        $this->assertEquals(now()->addDays(60)->toDateString(), $invoice->due_date->toDateString());
        $this->assertEquals('15600.00', $invoice->total_ttc);
        $this->post("/purchasing/orders/{$order->id}/generate-invoice")->assertSessionHasErrors('status');

        // Validate then pay in two steps.
        $this->post("/purchasing/invoices/{$invoice->id}/send")->assertRedirect()->assertSessionHasNoErrors();
        $this->post("/purchasing/invoices/{$invoice->id}/record-payment", ['amount' => 5600])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('partial', $invoice->fresh()->status);
        $this->post("/purchasing/invoices/{$invoice->id}/record-payment", ['amount' => 10000])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('paid', $invoice->fresh()->status);

        $this->get("/purchasing/orders/{$order->id}")
            ->assertInertia(fn (Assert $page) => $page->component('Purchasing/Orders/Show')->has('receptions', 1)->has('invoices', 1));
    }

    public function test_reception_of_an_order_without_lines_does_not_mark_it_received(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['reception_notes.confirm']);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->confirmed()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);
        $note = ReceptionNote::factory()->draft()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $supplier->id]);

        $this->actingAs($user)->post("/purchasing/receptions/{$note->id}/confirm")->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNotEquals('received', $order->fresh()->status);
    }

    public function test_index_pages_render_and_are_isolated(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->allPermissions());
        Supplier::factory()->create(['company_id' => $company->id, 'name' => 'Mine']);
        $foreign = Supplier::factory()->create(['name' => 'Foreign']);

        foreach ([
            '/purchasing/suppliers'  => 'Purchasing/Suppliers/Index',
            '/purchasing/requests'   => 'Purchasing/Requests/Index',
            '/purchasing/orders'     => 'Purchasing/Orders/Index',
            '/purchasing/receptions' => 'Purchasing/Receptions/Index',
            '/purchasing/invoices'   => 'Purchasing/Invoices/Index',
        ] as $url => $component) {
            $this->actingAs($user)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
        }

        $this->actingAs($user)->get('/purchasing/suppliers')->assertInertia(fn (Assert $page) => $page->has('suppliers.data', 1));
        $this->actingAs($user)->get("/purchasing/suppliers/{$foreign->id}")->assertNotFound();
    }

    public function test_order_rejects_another_companys_product(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['purchase_orders.create']);
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $foreignProduct = \App\Models\Inventory\Product::factory()->create();

        $this->actingAs($user)->post('/purchasing/orders', [
            'supplier_id' => $supplier->id,
            'order_date'  => now()->toDateString(),
            'lines'       => [['product_id' => $foreignProduct->id, 'description' => 'x', 'quantity' => 1, 'unit_price_ht' => 1]],
        ])->assertSessionHasErrors('lines.0.product_id');
    }
}
