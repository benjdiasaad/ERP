<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Company\Company;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Customer;
use App\Models\Sales\Invoice;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class InvoicePagesTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function createInvoice(Company $company, $user, array $overrides = []): Invoice
    {
        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Atlas']);

        $this->actingAs($user)->post('/sales/invoices', array_merge([
            'customer_id'  => $customer->id,
            'invoice_date' => now()->toDateString(),
            'due_date'     => now()->addDays(30)->toDateString(),
            'lines'        => [['description' => 'Prestation', 'quantity' => 1, 'unit_price_ht' => 1000, 'tax_rate' => 20]],
        ], $overrides))->assertRedirect()->assertSessionHasNoErrors();

        return Invoice::where('customer_id', $customer->id)->latest('id')->firstOrFail();
    }

    public function test_create_and_list_unpaid(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['invoices.create', 'invoices.view_any', 'invoices.send']);
        $invoice = $this->createInvoice($company, $user);
        $this->assertEquals('1200.00', $invoice->amount_due);

        $this->actingAs($user)->post("/sales/invoices/{$invoice->id}/send")->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($user)->get('/sales/invoices?status=unpaid')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Sales/Invoices/Index')->has('invoices.data', 1)->where('counts.sent.due', 1200));
    }

    public function test_partial_then_full_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['invoices.create', 'invoices.send', 'invoices.record_payment']);
        $invoice = $this->createInvoice($company, $user);

        // Draft invoices cannot receive payments.
        $this->actingAs($user)->post("/sales/invoices/{$invoice->id}/record-payment", ['amount' => 100])->assertSessionHasErrors('status');

        $this->actingAs($user)->post("/sales/invoices/{$invoice->id}/send");

        $this->actingAs($user)->post("/sales/invoices/{$invoice->id}/record-payment", ['amount' => 200])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('partial', $invoice->fresh()->status);
        $this->assertEquals('1000.00', $invoice->fresh()->amount_due);

        $this->actingAs($user)->post("/sales/invoices/{$invoice->id}/record-payment", ['amount' => 5000])->assertSessionHasErrors('amount');

        $this->actingAs($user)->post("/sales/invoices/{$invoice->id}/record-payment", ['amount' => 1000])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('paid', $invoice->fresh()->status);
    }

    public function test_overdue_invoice_is_flagged_when_viewed(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['invoices.create', 'invoices.send', 'invoices.view']);
        $invoice = $this->createInvoice($company, $user, [
            'invoice_date' => now()->subDays(40)->toDateString(),
            'due_date'     => now()->subDays(10)->toDateString(),
        ]);
        $this->actingAs($user)->post("/sales/invoices/{$invoice->id}/send");

        $this->actingAs($user)->get("/sales/invoices/{$invoice->id}")
            ->assertInertia(fn (Assert $page) => $page->component('Sales/Invoices/Show')->where('invoice.data.status', 'overdue'));
    }

    public function test_credit_note_and_cancel(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions([
            'invoices.create', 'invoices.send', 'invoices.create_credit_note', 'invoices.cancel',
        ]);
        $invoice = $this->createInvoice($company, $user);
        $this->actingAs($user)->post("/sales/invoices/{$invoice->id}/send");

        $this->actingAs($user)->post("/sales/invoices/{$invoice->id}/create-credit-note", ['reason' => 'Retour'])
            ->assertRedirect()->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue(CreditNote::where('invoice_id', $invoice->id)->exists());

        $this->actingAs($user)->post("/sales/invoices/{$invoice->id}/cancel", ['reason' => 'Erreur'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals('cancelled', $invoice->fresh()->status);
    }

    public function test_cannot_open_another_companys_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['invoices.view', 'invoices.create']);
        ['user' => $other, 'company' => $otherCompany] = $this->actingWithPermissions(['invoices.create']);
        $foreign = $this->createInvoice($otherCompany, $other);

        $this->actingAs($user)->get("/sales/invoices/{$foreign->id}")->assertNotFound();
    }
}
