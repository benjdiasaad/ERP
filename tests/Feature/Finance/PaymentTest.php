<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Finance\BankAccount;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentMethod;
use App\Models\Sales\Customer;
use App\Models\Sales\Invoice;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Purchasing\Supplier;
use App\Services\Finance\PaymentService;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PaymentService::class);
    }

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

    private function paymentPayload(
        Invoice|PurchaseInvoice $payable,
        BankAccount $bankAccount,
        PaymentMethod $paymentMethod,
        float $amount = 100,
        array $overrides = []
    ): array {
        return array_merge([
            'payable_type'      => $payable::class,
            'payable_id'        => $payable->id,
            'direction'         => $payable instanceof Invoice ? 'incoming' : 'outgoing',
            'amount'            => $amount,
            'payment_method_id' => $paymentMethod->id,
            'bank_account_id'   => $bankAccount->id,
            'payment_date'      => now()->toDateString(),
            'status'            => 'pending',
        ], $overrides);
    }

    private function createInvoiceWithBalance(Company $company, float $totalTtc = 1000): Invoice
    {
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        return Invoice::factory()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'total_ttc'   => $totalTtc,
            'amount_paid' => 0,
            'amount_due'  => $totalTtc,
            'status'      => 'sent',
        ]);
    }

    private function createPurchaseInvoiceWithBalance(Company $company, float $totalTtc = 1000): PurchaseInvoice
    {
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        return PurchaseInvoice::factory()->create([
            'company_id'  => $company->id,
            'supplier_id' => $supplier->id,
            'total_ttc'   => $totalTtc,
            'amount_paid' => 0,
            'amount_due'  => $totalTtc,
            'status'      => 'received',
        ]);
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_payments(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.view_any']);

        Payment::factory()->count(3)->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payments');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'reference', 'amount', 'status']]]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/payments');

        $response->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payments');

        $response->assertForbidden();
    }

    public function test_index_only_returns_payments_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['payments.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['payments.view_any']);

        Payment::factory()->create(['company_id' => $companyA->id, 'reference' => 'PAY-A-001']);
        Payment::factory()->create(['company_id' => $companyB->id, 'reference' => 'PAY-B-001']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/payments');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('PAY-A-001', $response->json('data.0.reference'));
    }

    public function test_index_can_filter_by_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.view_any']);

        Payment::factory()->create(['company_id' => $company->id, 'status' => 'pending']);
        Payment::factory()->create(['company_id' => $company->id, 'status' => 'confirmed']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payments?status=pending');

        $response->assertOk();
        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals(['pending'], $statuses);
    }

    public function test_index_can_filter_by_direction(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.view_any']);

        Payment::factory()->create(['company_id' => $company->id, 'direction' => 'incoming']);
        Payment::factory()->create(['company_id' => $company->id, 'direction' => 'outgoing']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payments?direction=incoming');

        $response->assertOk();
        $directions = collect($response->json('data'))->pluck('direction')->unique()->toArray();
        $this->assertEquals(['incoming'], $directions);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $invoice = $this->createInvoiceWithBalance($company, 1000);
        $bankAccount = BankAccount::factory()->create(['company_id' => $company->id, 'balance' => 5000]);
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $this->paymentPayload($invoice, $bankAccount, $paymentMethod, 500));

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.amount', 500);
        $response->assertJsonPath('data.direction', 'incoming');

        $this->assertDatabaseHas('payments', [
            'company_id' => $company->id,
            'status'     => 'pending',
        ]);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $invoice = $this->createInvoiceWithBalance($company);
        $bankAccount = BankAccount::factory()->create(['company_id' => $company->id]);
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $this->paymentPayload($invoice, $bankAccount, $paymentMethod));

        $response->assertForbidden();
    }

    public function test_store_requires_amount(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $invoice = $this->createInvoiceWithBalance($company);
        $bankAccount = BankAccount::factory()->create(['company_id' => $company->id]);
        $paymentMethod = PaymentMethod::factory()->create();

        $payload = $this->paymentPayload($invoice, $bankAccount, $paymentMethod);
        unset($payload['amount']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('amount');
    }

    public function test_store_requires_payable(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $bankAccount = BankAccount::factory()->create(['company_id' => $company->id]);
        $paymentMethod = PaymentMethod::factory()->create();

        $payload = [
            'amount'            => 100,
            'payment_method_id' => $paymentMethod->id,
            'bank_account_id'   => $bankAccount->id,
            'payment_date'      => now()->toDateString(),
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $payload);

        $response->assertUnprocessable();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.view']);

        $payment = Payment::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/payments/{$payment->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $payment->id);
        $response->assertJsonPath('data.reference', $payment->reference);
    }

    public function test_show_returns_404_for_nonexistent_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.view']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payments/99999');

        $response->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $payment = Payment::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/payments/{$payment->id}");

        $response->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_pending_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.update']);

        $payment = Payment::factory()->create(['company_id' => $company->id, 'status' => 'pending']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/payments/{$payment->id}", [
                'notes' => 'Updated notes',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.notes', 'Updated notes');

        $this->assertDatabaseHas('payments', [
            'id'    => $payment->id,
            'notes' => 'Updated notes',
        ]);
    }

    public function test_update_cannot_modify_confirmed_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.update']);

        $payment = Payment::factory()->create(['company_id' => $company->id, 'status' => 'confirmed']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/payments/{$payment->id}", [
                'notes' => 'Hacked',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('status');
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $payment = Payment::factory()->create(['company_id' => $company->id, 'status' => 'pending']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/payments/{$payment->id}", ['notes' => 'Hacked']);

        $response->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_pending_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.delete']);

        $payment = Payment::factory()->create(['company_id' => $company->id, 'status' => 'pending']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/payments/{$payment->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
    }

    public function test_destroy_cannot_delete_confirmed_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.delete']);

        $payment = Payment::factory()->create(['company_id' => $company->id, 'status' => 'confirmed']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/payments/{$payment->id}");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('status');
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $payment = Payment::factory()->create(['company_id' => $company->id, 'status' => 'pending']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/payments/{$payment->id}");

        $response->assertForbidden();
    }

    // ─── Confirm (Full Flow: Journal + Balance Updates) ────────────────────────

    public function test_confirm_transitions_pending_to_confirmed(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $invoice = $this->createInvoiceWithBalance($company, 1000);
        $bankAccount = BankAccount::factory()->create(['company_id' => $company->id, 'balance' => 5000]);
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $this->paymentPayload($invoice, $bankAccount, $paymentMethod, 500));

        $payment = Payment::find($response->json('data.id'));

        $confirmResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment->id}/confirm");

        $confirmResponse->assertOk();
        $confirmResponse->assertJsonPath('data.status', 'confirmed');
        $confirmResponse->assertJsonPath('data.confirmed_by', $user->id);
        $this->assertNotNull($confirmResponse->json('data.confirmed_at'));

        $this->assertDatabaseHas('payments', [
            'id'     => $payment->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_confirm_creates_journal_entry(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $invoice = $this->createInvoiceWithBalance($company, 1000);
        $bankAccount = BankAccount::factory()->create(['company_id' => $company->id, 'balance' => 5000]);
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $this->paymentPayload($invoice, $bankAccount, $paymentMethod, 500));

        $payment = Payment::find($response->json('data.id'));

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment->id}/confirm");

        // Verify journal entry was created
        $journalEntry = JournalEntry::where('reference', "JE-{$payment->reference}")->first();
        $this->assertNotNull($journalEntry);
        $this->assertEquals('posted', $journalEntry->status);
        $this->assertCount(2, $journalEntry->lines);
    }

    public function test_confirm_updates_invoice_balance_for_incoming_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $invoice = $this->createInvoiceWithBalance($company, 1000);
        $bankAccount = BankAccount::factory()->create(['company_id' => $company->id, 'balance' => 5000]);
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $this->paymentPayload($invoice, $bankAccount, $paymentMethod, 500));

        $payment = Payment::find($response->json('data.id'));

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment->id}/confirm");

        $invoice->refresh();

        $this->assertEquals(500, $invoice->amount_paid);
        $this->assertEquals(500, $invoice->amount_due);
        $this->assertEquals('partial', $invoice->status);
    }

    public function test_confirm_marks_invoice_as_paid_when_fully_paid(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $invoice = $this->createInvoiceWithBalance($company, 1000);
        $bankAccount = BankAccount::factory()->create(['company_id' => $company->id, 'balance' => 5000]);
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $this->paymentPayload($invoice, $bankAccount, $paymentMethod, 1000));

        $payment = Payment::find($response->json('data.id'));

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment->id}/confirm");

        $invoice->refresh();

        $this->assertEquals(1000, $invoice->amount_paid);
        $this->assertEquals(0, $invoice->amount_due);
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_confirm_updates_bank_account_balance_for_incoming_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $invoice = $this->createInvoiceWithBalance($company, 1000);
        $bankAccount = BankAccount::factory()->create(['company_id' => $company->id, 'balance' => 5000]);
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $this->paymentPayload($invoice, $bankAccount, $paymentMethod, 500));

        $payment = Payment::find($response->json('data.id'));

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment->id}/confirm");

        $bankAccount->refresh();

        $this->assertEquals(5500, $bankAccount->balance);
    }

    public function test_confirm_decreases_bank_account_balance_for_outgoing_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $purchaseInvoice = $this->createPurchaseInvoiceWithBalance($company, 1000);
        $bankAccount = BankAccount::factory()->create(['company_id' => $company->id, 'balance' => 5000]);
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $this->paymentPayload($purchaseInvoice, $bankAccount, $paymentMethod, 500));

        $payment = Payment::find($response->json('data.id'));

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment->id}/confirm");

        $bankAccount->refresh();

        $this->assertEquals(4500, $bankAccount->balance);
    }

    public function test_confirm_cannot_confirm_already_confirmed_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $invoice = $this->createInvoiceWithBalance($company, 1000);
        $bankAccount = BankAccount::factory()->create(['company_id' => $company->id, 'balance' => 5000]);
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $this->paymentPayload($invoice, $bankAccount, $paymentMethod, 500));

        $payment = Payment::find($response->json('data.id'));

        // Confirm once
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment->id}/confirm")
            ->assertOk();

        // Try to confirm again
        $confirmResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment->id}/confirm");

        $confirmResponse->assertUnprocessable();
        $confirmResponse->assertJsonValidationErrors('status');
    }

    public function test_confirm_requires_bank_account(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $invoice = $this->createInvoiceWithBalance($company, 1000);
        $paymentMethod = PaymentMethod::factory()->create();

        $payload = [
            'payable_type'      => Invoice::class,
            'payable_id'        => $invoice->id,
            'direction'         => 'incoming',
            'amount'            => 500,
            'payment_method_id' => $paymentMethod->id,
            'payment_date'      => now()->toDateString(),
            'status'            => 'pending',
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $payload);

        $payment = Payment::find($response->json('data.id'));

        $confirmResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment->id}/confirm");

        $confirmResponse->assertUnprocessable();
        $confirmResponse->assertJsonValidationErrors('bank_account_id');
    }

    // ─── Cancel ───────────────────────────────────────────────────────────────

    public function test_user_can_cancel_pending_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $payment = Payment::factory()->create(['company_id' => $company->id, 'status' => 'pending']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment->id}/cancel", [
                'reason' => 'Duplicate payment',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cancel_cannot_cancel_confirmed_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $payment = Payment::factory()->create(['company_id' => $company->id, 'status' => 'confirmed']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment->id}/cancel");

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('status');
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_payment_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['payments.view']);

        $payment = Payment::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/payments/{$payment->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_user_from_company_b_cannot_confirm_payment_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['payments.create']);

        $payment = Payment::factory()->create(['company_id' => $companyA->id, 'status' => 'pending']);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->postJson("/api/payments/{$payment->id}/confirm");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_index_does_not_leak_payments_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['payments.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['payments.view_any']);

        Payment::factory()->count(2)->create(['company_id' => $companyA->id]);
        Payment::factory()->count(3)->create(['company_id' => $companyB->id]);

        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/payments');

        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson('/api/payments');

        $responseA->assertOk();
        $responseB->assertOk();
        $this->assertCount(2, $responseA->json('data'));
        $this->assertCount(3, $responseB->json('data'));
    }

    // ─── Full Cycle Tests ─────────────────────────────────────────────────────

    public function test_full_payment_cycle_incoming_partial_then_full(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $invoice = $this->createInvoiceWithBalance($company, 1000);
        $bankAccount = BankAccount::factory()->create(['company_id' => $company->id, 'balance' => 5000]);
        $paymentMethod = PaymentMethod::factory()->create();

        // First partial payment: 600
        $response1 = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $this->paymentPayload($invoice, $bankAccount, $paymentMethod, 600));

        $payment1 = Payment::find($response1->json('data.id'));

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment1->id}/confirm");

        $invoice->refresh();
        $this->assertEquals(600, $invoice->amount_paid);
        $this->assertEquals(400, $invoice->amount_due);
        $this->assertEquals('partial', $invoice->status);

        // Second payment: 400 (full payment)
        $response2 = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $this->paymentPayload($invoice, $bankAccount, $paymentMethod, 400));

        $payment2 = Payment::find($response2->json('data.id'));

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment2->id}/confirm");

        $invoice->refresh();
        $this->assertEquals(1000, $invoice->amount_paid);
        $this->assertEquals(0, $invoice->amount_due);
        $this->assertEquals('paid', $invoice->status);

        // Verify bank account balance increased by 1000
        $bankAccount->refresh();
        $this->assertEquals(6000, $bankAccount->balance);
    }

    public function test_full_payment_cycle_outgoing_payment(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payments.create']);

        $purchaseInvoice = $this->createPurchaseInvoiceWithBalance($company, 1000);
        $bankAccount = BankAccount::factory()->create(['company_id' => $company->id, 'balance' => 5000]);
        $paymentMethod = PaymentMethod::factory()->create();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payments', $this->paymentPayload($purchaseInvoice, $bankAccount, $paymentMethod, 1000));

        $payment = Payment::find($response->json('data.id'));

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/payments/{$payment->id}/confirm");

        $purchaseInvoice->refresh();
        $this->assertEquals(1000, $purchaseInvoice->amount_paid);
        $this->assertEquals(0, $purchaseInvoice->amount_due);
        $this->assertEquals('paid', $purchaseInvoice->status);

        // Verify bank account balance decreased by 1000
        $bankAccount->refresh();
        $this->assertEquals(4000, $bankAccount->balance);
    }
}
