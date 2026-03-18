<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Sales\Customer;
use App\Models\Sales\Invoice;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
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

    /** Create a sent invoice with known totals for payment tests. */
    private function makeSentInvoice(Company $company, float $totalTtc = 1000.00): Invoice
    {
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        return Invoice::factory()->sent()->withTotals($totalTtc)->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'amount_paid' => 0.00,
            'amount_due'  => $totalTtc,
        ]);
    }

    // ─── Full Payment ─────────────────────────────────────────────────────────

    public function test_full_payment_sets_status_to_paid_and_amount_due_to_zero(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.record_payment']);

        $invoice = $this->makeSentInvoice($company, 1000.00);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/record-payment", [
                'amount'       => 1000.00,
                'payment_date' => now()->toDateString(),
            ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'paid'])
            ->assertJsonFragment(['amount_due' => '0.00']);

        $this->assertDatabaseHas('invoices', [
            'id'          => $invoice->id,
            'status'      => 'paid',
            'amount_paid' => '1000.00',
            'amount_due'  => '0.00',
        ]);
    }

    // ─── Partial Payment ──────────────────────────────────────────────────────

    public function test_partial_payment_sets_status_to_partial_and_reduces_amount_due(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.record_payment']);

        $invoice = $this->makeSentInvoice($company, 1000.00);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/record-payment", [
                'amount' => 400.00,
            ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'partial']);

        $this->assertDatabaseHas('invoices', [
            'id'          => $invoice->id,
            'status'      => 'partial',
            'amount_paid' => '400.00',
            'amount_due'  => '600.00',
        ]);
    }

    public function test_multiple_partial_payments_summing_to_full_amount_sets_status_to_paid(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.record_payment']);

        $invoice = $this->makeSentInvoice($company, 900.00);

        // First partial payment
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/record-payment", ['amount' => 300.00])
            ->assertOk()
            ->assertJsonFragment(['status' => 'partial']);

        // Second partial payment
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/record-payment", ['amount' => 300.00])
            ->assertOk()
            ->assertJsonFragment(['status' => 'partial']);

        // Final payment — completes the invoice
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/record-payment", ['amount' => 300.00])
            ->assertOk()
            ->assertJsonFragment(['status' => 'paid'])
            ->assertJsonFragment(['amount_due' => '0.00']);

        $this->assertDatabaseHas('invoices', [
            'id'          => $invoice->id,
            'status'      => 'paid',
            'amount_paid' => '900.00',
            'amount_due'  => '0.00',
        ]);
    }

    // ─── Overdue Detection ────────────────────────────────────────────────────

    public function test_overdue_invoice_appears_in_overdue_endpoint(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.view_any']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        // Invoice with past due_date and sent status (unpaid)
        $overdueInvoice = Invoice::factory()->sent()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'due_date'    => now()->subDays(10)->toDateString(),
            'amount_due'  => 500.00,
        ]);

        // Invoice with future due_date — should NOT appear
        Invoice::factory()->sent()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'due_date'    => now()->addDays(30)->toDateString(),
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/invoices/overdue');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($overdueInvoice->id, $ids);
    }

    public function test_paid_invoice_does_not_appear_in_overdue_endpoint(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.view_any']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $paidInvoice = Invoice::factory()->paid()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'due_date'    => now()->subDays(5)->toDateString(),
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/invoices/overdue');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($paidInvoice->id, $ids);
    }

    // ─── Error Cases ──────────────────────────────────────────────────────────

    public function test_cannot_record_payment_on_cancelled_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.record_payment']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->cancelled()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/record-payment", ['amount' => 100.00])
            ->assertStatus(422);
    }

    public function test_cannot_record_payment_on_draft_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.record_payment']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->draft()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/record-payment", ['amount' => 100.00])
            ->assertStatus(422);
    }

    public function test_cannot_overpay_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.record_payment']);

        $invoice = $this->makeSentInvoice($company, 500.00);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/record-payment", ['amount' => 999.00])
            ->assertStatus(422);
    }

    public function test_payment_amount_must_be_positive(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.record_payment']);

        $invoice = $this->makeSentInvoice($company, 500.00);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/record-payment", ['amount' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_record_payment_requires_record_payment_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $invoice = $this->makeSentInvoice($company, 500.00);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/record-payment", ['amount' => 100.00])
            ->assertForbidden();
    }

    public function test_record_payment_requires_authentication(): void
    {
        $this->postJson('/api/invoices/1/record-payment', ['amount' => 100])->assertUnauthorized();
    }
}
