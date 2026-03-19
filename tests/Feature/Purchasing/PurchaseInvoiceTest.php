<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Purchasing\Supplier;
use Tests\TestCase;

class PurchaseInvoiceTest extends TestCase
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

    private function invoicePayload(int $supplierId): array
    {
        return [
            'supplier_id'  => $supplierId,
            'invoice_date' => now()->toDateString(),
            'due_date'     => now()->addDays(30)->toDateString(),
            'lines'        => [
                [
                    'description'   => 'Service A',
                    'quantity'      => 2,
                    'unit_price_ht' => 500,
                    'tax_rate'      => 20,
                ],
            ],
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_purchase_invoices(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.view_any']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        PurchaseInvoice::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/purchase-invoices')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/purchase-invoices')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/purchase-invoices')
            ->assertForbidden();
    }

    public function test_index_only_returns_invoices_from_current_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.view_any']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        PurchaseInvoice::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'reference' => 'FAF-MY']);

        $otherCompany  = Company::factory()->create();
        $otherSupplier = Supplier::factory()->create(['company_id' => $otherCompany->id]);
        PurchaseInvoice::factory()->create(['company_id' => $otherCompany->id, 'supplier_id' => $otherSupplier->id, 'reference' => 'FAF-OTHER']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/purchase-invoices');

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference')->toArray();
        $this->assertContains('FAF-MY', $refs);
        $this->assertNotContains('FAF-OTHER', $refs);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_purchase_invoice_with_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.create']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-invoices', $this->invoicePayload($supplier->id));

        $response->assertCreated()
            ->assertJsonFragment(['supplier_id' => $supplier->id]);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('purchase_invoice_lines', ['purchase_invoice_id' => $id, 'description' => 'Service A']);
    }

    public function test_store_auto_generates_reference_in_faf_format(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.create']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-invoices', $this->invoicePayload($supplier->id));

        $response->assertCreated();
        $this->assertMatchesRegularExpression('/^FAF-\d{4}-\d{5}$/', $response->json('data.reference'));
    }

    public function test_store_calculates_totals_correctly(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.create']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-invoices', $this->invoicePayload($supplier->id));

        $response->assertCreated();
        // qty=2, price=500 → subtotal=1000, tax=20% → tax=200, total_ttc=1200
        $this->assertEquals('1000.00', $response->json('data.subtotal_ht'));
        $this->assertEquals('1200.00', $response->json('data.total_ttc'));
        $this->assertEquals('1200.00', $response->json('data.amount_due'));
    }

    public function test_store_requires_supplier_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-invoices', ['invoice_date' => now()->toDateString(), 'lines' => [['description' => 'A', 'quantity' => 1, 'unit_price_ht' => 10]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_store_requires_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.create']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-invoices', ['supplier_id' => $supplier->id, 'invoice_date' => now()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-invoices', $this->invoicePayload($supplier->id))
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_purchase_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.view']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/purchase-invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $invoice->id]);
    }

    public function test_show_returns_404_for_nonexistent_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/purchase-invoices/99999')
            ->assertNotFound();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_draft_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.update']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/purchase-invoices/{$invoice->id}", ['notes' => 'Updated notes'])
            ->assertOk()
            ->assertJsonFragment(['notes' => 'Updated notes']);
    }

    public function test_update_non_draft_invoice_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.update']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->sent()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/purchase-invoices/{$invoice->id}", ['notes' => 'Hacked'])
            ->assertUnprocessable();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_delete_draft_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.delete']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/purchase-invoices/{$invoice->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('purchase_invoices', ['id' => $invoice->id]);
    }

    public function test_delete_non_draft_invoice_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.delete']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->sent()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/purchase-invoices/{$invoice->id}")
            ->assertUnprocessable();
    }

    // ─── Send ─────────────────────────────────────────────────────────────────

    public function test_draft_invoice_can_be_sent(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.send']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/send")
            ->assertOk()
            ->assertJsonFragment(['status' => 'sent']);
    }

    public function test_send_non_draft_invoice_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.send']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->sent()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/send")
            ->assertUnprocessable();
    }

    public function test_send_requires_send_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/send")
            ->assertForbidden();
    }

    // ─── Cancel ───────────────────────────────────────────────────────────────

    public function test_draft_invoice_can_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.cancel']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/cancel")
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_sent_invoice_can_be_cancelled_with_reason(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.cancel']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->sent()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/cancel", ['reason' => 'Duplicate invoice'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_paid_invoice_cannot_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.cancel']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->paid()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_cancel_requires_cancel_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/cancel")
            ->assertForbidden();
    }

    // ─── Record Payment ───────────────────────────────────────────────────────

    public function test_can_record_partial_payment_on_sent_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.record_payment']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->sent()->create([
            'company_id'  => $company->id,
            'supplier_id' => $supplier->id,
            'total_ttc'   => 1000,
            'amount_paid' => 0,
            'amount_due'  => 1000,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/record-payment", ['amount' => 400])
            ->assertOk()
            ->assertJsonFragment(['status' => 'partial'])
            ->assertJsonFragment(['amount_paid' => '400.00'])
            ->assertJsonFragment(['amount_due' => '600.00']);
    }

    public function test_record_payment_that_covers_full_amount_marks_as_paid(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.record_payment']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->sent()->create([
            'company_id'  => $company->id,
            'supplier_id' => $supplier->id,
            'total_ttc'   => 1000,
            'amount_paid' => 0,
            'amount_due'  => 1000,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/record-payment", ['amount' => 1000])
            ->assertOk()
            ->assertJsonFragment(['status' => 'paid']);
    }

    public function test_record_payment_exceeding_amount_due_fails(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.record_payment']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->sent()->create([
            'company_id'  => $company->id,
            'supplier_id' => $supplier->id,
            'total_ttc'   => 1000,
            'amount_paid' => 0,
            'amount_due'  => 1000,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/record-payment", ['amount' => 1500])
            ->assertUnprocessable();
    }

    public function test_record_payment_on_draft_invoice_fails(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.record_payment']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->draft()->create([
            'company_id'  => $company->id,
            'supplier_id' => $supplier->id,
            'total_ttc'   => 1000,
            'amount_due'  => 1000,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/record-payment", ['amount' => 500])
            ->assertUnprocessable();
    }

    public function test_record_payment_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->sent()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/record-payment", ['amount' => 100])
            ->assertForbidden();
    }

    // ─── Mark Paid ────────────────────────────────────────────────────────────

    public function test_can_mark_sent_invoice_as_paid(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.record_payment']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->sent()->create([
            'company_id'  => $company->id,
            'supplier_id' => $supplier->id,
            'total_ttc'   => 1000,
            'amount_paid' => 0,
            'amount_due'  => 1000,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/mark-paid")
            ->assertOk()
            ->assertJsonFragment(['status' => 'paid'])
            ->assertJsonFragment(['amount_due' => '0.00']);
    }

    public function test_mark_paid_on_draft_invoice_fails(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_invoices.record_payment']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $invoice  = PurchaseInvoice::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$invoice->id}/mark-paid")
            ->assertUnprocessable();
    }

    // ─── Full Lifecycle ───────────────────────────────────────────────────────

    public function test_full_lifecycle_create_send_record_payment_paid(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, [
            'purchase_invoices.create',
            'purchase_invoices.send',
            'purchase_invoices.record_payment',
        ]);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        // Create
        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-invoices', $this->invoicePayload($supplier->id));
        $createResponse->assertCreated();
        $id = $createResponse->json('data.id');

        // Send
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$id}/send")
            ->assertOk()
            ->assertJsonFragment(['status' => 'sent']);

        // Record full payment
        $totalTtc = $createResponse->json('data.total_ttc');
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-invoices/{$id}/record-payment", ['amount' => (float) $totalTtc])
            ->assertOk()
            ->assertJsonFragment(['status' => 'paid']);

        $this->assertDatabaseHas('purchase_invoices', ['id' => $id, 'status' => 'paid']);
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_invoice_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['purchase_invoices.view']);
        $this->giveUserPermissions($userB, $companyB, ['purchase_invoices.view']);

        $supplier = Supplier::factory()->create(['company_id' => $companyA->id]);
        $invoice  = PurchaseInvoice::factory()->create(['company_id' => $companyA->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/purchase-invoices/{$invoice->id}")
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_update_invoice_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['purchase_invoices.update']);
        $this->giveUserPermissions($userB, $companyB, ['purchase_invoices.update']);

        $supplier = Supplier::factory()->create(['company_id' => $companyA->id]);
        $invoice  = PurchaseInvoice::factory()->draft()->create(['company_id' => $companyA->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/purchase-invoices/{$invoice->id}", ['notes' => 'Hijacked'])
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_delete_invoice_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['purchase_invoices.delete']);
        $this->giveUserPermissions($userB, $companyB, ['purchase_invoices.delete']);

        $supplier = Supplier::factory()->create(['company_id' => $companyA->id]);
        $invoice  = PurchaseInvoice::factory()->draft()->create(['company_id' => $companyA->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/purchase-invoices/{$invoice->id}")
            ->assertNotFound();
    }
}
