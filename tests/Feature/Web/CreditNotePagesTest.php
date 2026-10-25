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

class CreditNotePagesTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function sentInvoice($user, Customer $customer, float $price = 1000): Invoice
    {
        $this->actingAs($user)->post('/sales/invoices', [
            'customer_id'  => $customer->id,
            'invoice_date' => now()->toDateString(),
            'lines'        => [['description' => 'Prestation', 'quantity' => 1, 'unit_price_ht' => $price, 'tax_rate' => 20]],
        ])->assertSessionHasNoErrors();
        $invoice = Invoice::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->actingAs($user)->post("/sales/invoices/{$invoice->id}/send");

        return $invoice;
    }

    private function permissions(): array
    {
        return ['invoices.create', 'invoices.send', 'credit_notes.create', 'credit_notes.view', 'credit_notes.confirm', 'credit_notes.apply'];
    }

    public function test_create_confirm_and_apply_reduces_invoice_balance(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->permissions());
        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Atlas']);
        $invoice = $this->sentInvoice($user, $customer); // 1 200 TTC

        $this->actingAs($user)->post('/sales/credit-notes', [
            'customer_id' => $customer->id,
            'invoice_id'  => $invoice->id,
            'reason'      => 'Remise commerciale',
            'lines'       => [['description' => 'Remise', 'quantity' => 1, 'unit_price_ht' => 250, 'tax_rate' => 20]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $note = CreditNote::firstOrFail();
        $this->assertEquals('300.00', $note->total_ttc);

        $this->actingAs($user)->post("/sales/credit-notes/{$note->id}/confirm")->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($user)->get("/sales/credit-notes/{$note->id}")
            ->assertInertia(fn (Assert $page) => $page->component('Sales/CreditNotes/Show')->has('invoices', 1));

        $this->actingAs($user)->post("/sales/credit-notes/{$note->id}/apply", ['invoice_id' => $invoice->id])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals('applied', $note->fresh()->status);
        $this->assertEquals('900.00', $invoice->fresh()->amount_due);
        $this->assertEquals('partial', $invoice->fresh()->status);
    }

    public function test_cannot_apply_to_another_customers_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->permissions());
        $atlas = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Atlas']);
        $other = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Other']);
        $otherInvoice = $this->sentInvoice($user, $other);

        $this->actingAs($user)->post('/sales/credit-notes', [
            'customer_id' => $atlas->id,
            'lines'       => [['description' => 'Remise', 'quantity' => 1, 'unit_price_ht' => 100, 'tax_rate' => 20]],
        ])->assertSessionHasNoErrors();
        $note = CreditNote::firstOrFail();
        $this->actingAs($user)->post("/sales/credit-notes/{$note->id}/confirm");

        $this->actingAs($user)->post("/sales/credit-notes/{$note->id}/apply", ['invoice_id' => $otherInvoice->id])
            ->assertSessionHasErrors('invoice_id');
        $this->assertEquals('1200.00', $otherInvoice->fresh()->amount_due);
    }

    public function test_api_apply_rejects_another_companys_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['credit_notes.apply']);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Atlas']);
        $note = CreditNote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id, 'status' => 'confirmed']);
        $foreignInvoice = Invoice::factory()->create(['company_id' => Company::factory()->create()->id, 'status' => 'sent']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/credit-notes/{$note->id}/apply", ['invoice_id' => $foreignInvoice->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invoice_id');
    }
}
