<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Customer;
use App\Models\Sales\Invoice;
use Tests\TestCase;

class CreditNoteTest extends TestCase
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

    private function creditNotePayload(int $customerId): array
    {
        return [
            'customer_id' => $customerId,
            'date'        => now()->toDateString(),
            'reason'      => 'Returned goods',
            'lines'       => [
                [
                    'description'    => 'Returned item A',
                    'quantity'       => 2,
                    'unit_price_ht'  => 100.00,
                    'discount_type'  => 'percentage',
                    'discount_value' => 0,
                    'tax_rate'       => 20,
                ],
            ],
        ];
    }

    private function allCreditNotePermissions(): array
    {
        return [
            'credit_notes.view_any', 'credit_notes.view', 'credit_notes.create',
            'credit_notes.update', 'credit_notes.delete', 'credit_notes.confirm',
            'credit_notes.apply',
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_credit_notes(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.view_any']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        CreditNote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/credit-notes')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/credit-notes')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/credit-notes')
            ->assertForbidden();
    }

    public function test_index_only_returns_credit_notes_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userA, $companyA, ['credit_notes.view_any']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        CreditNote::factory()->create([
            'company_id'  => $companyA->id,
            'customer_id' => $customerA->id,
            'reference'   => 'AV-MY-00001',
        ]);

        $companyB  = Company::factory()->create();
        $customerB = Customer::factory()->company()->create(['company_id' => $companyB->id]);
        CreditNote::factory()->create([
            'company_id'  => $companyB->id,
            'customer_id' => $customerB->id,
            'reference'   => 'AV-OTHER-00001',
        ]);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/credit-notes');

        $refs = collect($response->json('data'))->pluck('reference')->toArray();
        $this->assertContains('AV-MY-00001', $refs);
        $this->assertNotContains('AV-OTHER-00001', $refs);
    }

    public function test_index_can_filter_by_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.view_any']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        CreditNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        CreditNote::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/credit-notes?status=draft');

        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals(['draft'], $statuses);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_credit_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/credit-notes', $this->creditNotePayload($customer->id));

        $response->assertCreated()
            ->assertJsonFragment(['status' => 'draft'])
            ->assertJsonPath('data.customer_id', $customer->id);

        $this->assertDatabaseHas('credit_notes', ['company_id' => $company->id, 'customer_id' => $customer->id]);
    }

    public function test_store_auto_generates_reference(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/credit-notes', $this->creditNotePayload($customer->id));

        $response->assertCreated();
        $ref = $response->json('data.reference');
        $this->assertNotNull($ref);
        $this->assertMatchesRegularExpression('/^AV-\d{4}-\d{5}$/', $ref);
    }

    public function test_store_calculates_line_totals(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = [
            'customer_id' => $customer->id,
            'date'        => now()->toDateString(),
            'lines'       => [
                [
                    'description'    => 'Item X',
                    'quantity'       => 5,
                    'unit_price_ht'  => 200.00,
                    'discount_type'  => 'percentage',
                    'discount_value' => 10,
                    'tax_rate'       => 20,
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/credit-notes', $payload);

        $response->assertCreated();
        // subtotal_ht = 5 * 200 = 1000, discount = 100, after_discount = 900, tax = 180, ttc = 1080
        $this->assertEquals('1000.00', $response->json('data.subtotal_ht'));
        $this->assertEquals('180.00', $response->json('data.total_tax'));
        $this->assertEquals('1080.00', $response->json('data.total_ttc'));
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/credit-notes', $this->creditNotePayload($customer->id))
            ->assertForbidden();
    }

    public function test_store_requires_customer_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.create']);

        $payload = [
            'date'  => now()->toDateString(),
            'lines' => [['description' => 'X', 'quantity' => 1, 'unit_price_ht' => 100]],
        ];

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/credit-notes', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_requires_at_least_one_line(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = [
            'customer_id' => $customer->id,
            'date'        => now()->toDateString(),
            'lines'       => [],
        ];

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/credit-notes', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_credit_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.view']);

        $customer   = Customer::factory()->company()->create(['company_id' => $company->id]);
        $creditNote = CreditNote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/credit-notes/{$creditNote->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $creditNote->id]);
    }

    public function test_show_returns_404_for_nonexistent_credit_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/credit-notes/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer   = Customer::factory()->company()->create(['company_id' => $company->id]);
        $creditNote = CreditNote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/credit-notes/{$creditNote->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_draft_credit_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.update']);

        $customer   = Customer::factory()->company()->create(['company_id' => $company->id]);
        $creditNote = CreditNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/credit-notes/{$creditNote->id}", ['notes' => 'Updated notes'])
            ->assertOk()
            ->assertJsonFragment(['notes' => 'Updated notes']);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer   = Customer::factory()->company()->create(['company_id' => $company->id]);
        $creditNote = CreditNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/credit-notes/{$creditNote->id}", ['notes' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_cannot_update_confirmed_credit_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.update']);

        $customer   = Customer::factory()->company()->create(['company_id' => $company->id]);
        $creditNote = CreditNote::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/credit-notes/{$creditNote->id}", ['notes' => 'Should fail'])
            ->assertUnprocessable();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_draft_credit_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.delete']);

        $customer   = Customer::factory()->company()->create(['company_id' => $company->id]);
        $creditNote = CreditNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/credit-notes/{$creditNote->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('credit_notes', ['id' => $creditNote->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer   = Customer::factory()->company()->create(['company_id' => $company->id]);
        $creditNote = CreditNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/credit-notes/{$creditNote->id}")
            ->assertForbidden();
    }

    public function test_cannot_delete_confirmed_credit_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.delete']);

        $customer   = Customer::factory()->company()->create(['company_id' => $company->id]);
        $creditNote = CreditNote::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/credit-notes/{$creditNote->id}")
            ->assertUnprocessable();
    }

    // ─── Lifecycle: Confirm ───────────────────────────────────────────────────

    public function test_draft_credit_note_can_be_confirmed(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.confirm', 'credit_notes.update']);

        $customer   = Customer::factory()->company()->create(['company_id' => $company->id]);
        $creditNote = CreditNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/credit-notes/{$creditNote->id}/confirm")
            ->assertOk()
            ->assertJsonFragment(['status' => 'confirmed']);

        $this->assertDatabaseHas('credit_notes', ['id' => $creditNote->id, 'status' => 'confirmed']);
        $this->assertNotNull($creditNote->fresh()->confirmed_at);
    }

    public function test_confirmed_credit_note_cannot_be_confirmed_again(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.confirm', 'credit_notes.update']);

        $customer   = Customer::factory()->company()->create(['company_id' => $company->id]);
        $creditNote = CreditNote::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/credit-notes/{$creditNote->id}/confirm")
            ->assertUnprocessable();
    }

    public function test_confirm_requires_confirm_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer   = Customer::factory()->company()->create(['company_id' => $company->id]);
        $creditNote = CreditNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/credit-notes/{$creditNote->id}/confirm")
            ->assertForbidden();
    }

    // ─── Lifecycle: Apply ─────────────────────────────────────────────────────

    public function test_confirmed_credit_note_can_be_applied_to_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.apply', 'credit_notes.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'status'      => 'sent',
            'total_ttc'   => 1000.00,
            'amount_paid' => 0.00,
            'amount_due'  => 1000.00,
        ]);

        $creditNote = CreditNote::factory()->confirmed()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'total_ttc'   => 200.00,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/credit-notes/{$creditNote->id}/apply", [
                'invoice_id' => $invoice->id,
            ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'applied']);

        $this->assertDatabaseHas('credit_notes', ['id' => $creditNote->id, 'status' => 'applied']);
        $this->assertNotNull($creditNote->fresh()->applied_at);
    }

    public function test_apply_reduces_invoice_amount_due(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.apply', 'credit_notes.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'status'      => 'sent',
            'total_ttc'   => 1000.00,
            'amount_paid' => 0.00,
            'amount_due'  => 1000.00,
        ]);

        $creditNote = CreditNote::factory()->confirmed()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'total_ttc'   => 300.00,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/credit-notes/{$creditNote->id}/apply", [
                'invoice_id' => $invoice->id,
            ])
            ->assertOk();

        $updatedInvoice = $invoice->fresh();
        $this->assertEquals('700.00', $updatedInvoice->amount_due);
        $this->assertEquals('300.00', $updatedInvoice->amount_paid);
        $this->assertEquals('partial', $updatedInvoice->status);
    }

    public function test_apply_marks_invoice_as_paid_when_credit_covers_full_amount(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.apply', 'credit_notes.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'status'      => 'sent',
            'total_ttc'   => 500.00,
            'amount_paid' => 0.00,
            'amount_due'  => 500.00,
        ]);

        $creditNote = CreditNote::factory()->confirmed()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'total_ttc'   => 500.00,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/credit-notes/{$creditNote->id}/apply", [
                'invoice_id' => $invoice->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid', 'amount_due' => 0.00]);
    }

    public function test_draft_credit_note_cannot_be_applied(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.apply', 'credit_notes.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'status'      => 'sent',
            'amount_due'  => 1000.00,
        ]);

        $creditNote = CreditNote::factory()->draft()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/credit-notes/{$creditNote->id}/apply", [
                'invoice_id' => $invoice->id,
            ])
            ->assertUnprocessable();
    }

    public function test_apply_requires_apply_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'status'      => 'sent',
            'amount_due'  => 1000.00,
        ]);

        $creditNote = CreditNote::factory()->confirmed()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/credit-notes/{$creditNote->id}/apply", [
                'invoice_id' => $invoice->id,
            ])
            ->assertForbidden();
    }

    public function test_apply_requires_invoice_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['credit_notes.apply', 'credit_notes.update']);

        $customer   = Customer::factory()->company()->create(['company_id' => $company->id]);
        $creditNote = CreditNote::factory()->confirmed()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/credit-notes/{$creditNote->id}/apply", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invoice_id']);
    }

    // ─── Full Lifecycle ───────────────────────────────────────────────────────

    public function test_full_lifecycle_draft_to_applied(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $this->allCreditNotePermissions());

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $invoice = Invoice::factory()->create([
            'company_id'  => $company->id,
            'customer_id' => $customer->id,
            'status'      => 'sent',
            'total_ttc'   => 1000.00,
            'amount_paid' => 0.00,
            'amount_due'  => 1000.00,
        ]);

        // 1. Create (draft)
        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/credit-notes', $this->creditNotePayload($customer->id))
            ->assertCreated();

        $noteId = $createResponse->json('data.id');
        $this->assertEquals('draft', $createResponse->json('data.status'));

        // 2. Confirm -> confirmed
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/credit-notes/{$noteId}/confirm")
            ->assertOk()
            ->assertJsonFragment(['status' => 'confirmed']);

        // 3. Apply -> applied, invoice updated
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/credit-notes/{$noteId}/apply", ['invoice_id' => $invoice->id])
            ->assertOk()
            ->assertJsonFragment(['status' => 'applied']);

        $this->assertDatabaseHas('credit_notes', ['id' => $noteId, 'status' => 'applied']);
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_tenant_isolation_credit_note_not_visible_to_other_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['credit_notes.view']);

        $customerA  = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $creditNote = CreditNote::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id]);

        $this->assertTenantIsolation('/api/credit-notes', $userA, $userB, $creditNote->id);
    }
}
