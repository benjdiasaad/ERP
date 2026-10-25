<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Company\Company;
use App\Models\Sales\Customer;
use App\Models\Sales\Quote;
use App\Models\Sales\SalesOrder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\CRM\InteractsWithCrm;
use Tests\TestCase;

class QuotePagesTest extends TestCase
{
    use InteractsWithCrm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function quotePayload(Customer $customer, array $overrides = []): array
    {
        return array_merge([
            'customer_id'   => $customer->id,
            'date'          => now()->toDateString(),
            'validity_date' => now()->addDays(30)->toDateString(),
            'lines'         => [
                ['description' => 'Ordinateur portable', 'quantity' => 2, 'unit_price_ht' => 1000, 'discount_value' => 10, 'tax_rate' => 20],
                ['description' => 'Installation', 'quantity' => 1, 'unit_price_ht' => 500, 'tax_rate' => 20],
            ],
        ], $overrides);
    }

    private function draftQuote(Company $company, string $status = 'draft'): Quote
    {
        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Client']);

        return Quote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id, 'status' => $status]);
    }

    public function test_index_shows_counts_per_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['quotes.view_any']);
        $this->draftQuote($company);
        $sent = $this->draftQuote($company);
        $sent->update(['status' => 'sent']);

        $this->actingAs($user)->get('/sales/quotes?status=sent')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Quotes/Index')
                ->has('quotes.data', 1)
                ->where('counts.draft.count', 1)
                ->where('counts.sent.count', 1));
    }

    public function test_create_computes_totals(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['quotes.create', 'quotes.view']);
        $customer = Customer::factory()->create(['company_id' => $company->id, 'name' => 'Atlas']);

        $this->actingAs($user)->get("/sales/quotes/create?customer_id={$customer->id}")
            ->assertInertia(fn (Assert $page) => $page->component('Sales/Quotes/Create')->where('defaults.customer_id', $customer->id));

        $this->actingAs($user)->post('/sales/quotes', $this->quotePayload($customer))
            ->assertRedirect()->assertSessionHasNoErrors();

        // 2×1000 −10 % = 1800 + 500 = 2300 HT, TVA 460, TTC 2760
        $quote = Quote::where('customer_id', $customer->id)->firstOrFail();
        $this->assertEquals('draft', $quote->status);
        $this->assertEquals('2500.00', $quote->subtotal_ht);
        $this->assertEquals('200.00', $quote->total_discount);
        $this->assertEquals('460.00', $quote->total_tax);
        $this->assertEquals('2760.00', $quote->total_ttc);
    }

    public function test_create_rejects_customer_from_another_company(): void
    {
        ['user' => $user] = $this->actingWithPermissions(['quotes.create']);
        $foreign = Customer::factory()->create(['name' => 'Foreign']);

        $this->actingAs($user)->post('/sales/quotes', $this->quotePayload($foreign))
            ->assertSessionHasErrors('customer_id');

        $this->assertDatabaseCount('quotes', 0);
    }

    public function test_only_drafts_can_be_edited(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['quotes.update', 'quotes.view']);
        $quote = $this->draftQuote($company);

        $this->actingAs($user)->get("/sales/quotes/{$quote->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->component('Sales/Quotes/Edit'));

        $quote->update(['status' => 'sent']);

        $this->actingAs($user)->from("/sales/quotes/{$quote->id}")->get("/sales/quotes/{$quote->id}/edit")
            ->assertRedirect("/sales/quotes/{$quote->id}")
            ->assertSessionHasErrors('status');
    }

    public function test_full_workflow_send_accept_convert(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions([
            'quotes.view', 'quotes.send', 'quotes.accept', 'quotes.convert',
        ]);
        $quote = $this->draftQuote($company);

        $this->actingAs($user)->post("/sales/quotes/{$quote->id}/send")->assertSessionHasNoErrors();
        $this->assertEquals('sent', $quote->fresh()->status);

        $this->actingAs($user)->post("/sales/quotes/{$quote->id}/accept")->assertSessionHasNoErrors();
        $this->assertEquals('accepted', $quote->fresh()->status);

        $this->actingAs($user)->post("/sales/quotes/{$quote->id}/convert")->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertEquals('converted', $quote->fresh()->status);
        $this->assertTrue(SalesOrder::where('quote_id', $quote->id)->exists());

        $this->actingAs($user)->get("/sales/quotes/{$quote->id}")
            ->assertInertia(fn (Assert $page) => $page->where('quote.data.status', 'converted')->whereNot('salesOrder', null));
    }

    public function test_reject_with_reason_and_refused_transitions(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['quotes.reject', 'quotes.accept']);
        $quote = $this->draftQuote($company);

        // A draft cannot be accepted: the refusal comes back as an error.
        $this->actingAs($user)->post("/sales/quotes/{$quote->id}/accept")->assertSessionHasErrors('status');

        $quote->update(['status' => 'sent']);
        $this->actingAs($user)->post("/sales/quotes/{$quote->id}/reject", ['reason' => 'Trop cher'])->assertSessionHasNoErrors();

        $this->assertEquals('rejected', $quote->fresh()->status);
        $this->assertEquals('Trop cher', $quote->fresh()->rejection_reason);
    }

    public function test_duplicate_creates_new_draft(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['quotes.duplicate', 'quotes.view']);
        $quote = $this->draftQuote($company);
        $quote->update(['status' => 'rejected']);

        $response = $this->actingAs($user)->post("/sales/quotes/{$quote->id}/duplicate");

        $copy = Quote::where('id', '!=', $quote->id)->firstOrFail();
        $response->assertRedirect(route('sales.quotes.show', $copy));
        $this->assertEquals('draft', $copy->status);
    }

    public function test_converted_quote_cannot_be_deleted(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions(['quotes.delete']);
        $quote = $this->draftQuote($company, 'converted');

        $this->actingAs($user)->delete("/sales/quotes/{$quote->id}")->assertSessionHasErrors('status');
        $this->assertNotSoftDeleted('quotes', ['id' => $quote->id]);
    }

    public function test_send_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->actingWithPermissions($this->modulePermissions('quotes'));
        $quote = $this->draftQuote($company);

        $this->actingAs($user)->post("/sales/quotes/{$quote->id}/send")->assertForbidden();
    }
}
