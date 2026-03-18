<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Sales\Customer;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceLine;
use Tests\TestCase;

class InvoiceTest extends TestCase
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

    private function invoicePayload(int $customerId): array
    {
        return [
            'customer_id'  => $customerId,
            'invoice_date' => now()->toDateString(),
            'due_date'     => now()->addDays(30)->toDateString(),
            'lines'        => [
                [
                    'description'    => 'Service A',
                    'quantity'       => 2,
                    'unit_price_ht'  => 500.00,
                    'discount_type'  => null,
                    'discount_value' => 0,
                    'tax_rate'       => 20,
                ],
            ],
        ];
    }

    private function allInvoicePermissions(): array
    {
        return [
            'invoices.view_any', 'invoices.view', 'invoices.create',
            'invoices.update', 'invoices.delete', 'invoices.send',
            'invoices.cancel', 'invoices.record_payment', 'invoices.print',
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_invoices(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.view_any']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        Invoice::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/invoices')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_index_is_paginated(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.view_any']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        Invoice::factory()->count(3)->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/invoices?per_page=2');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/invoices')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/invoices')
            ->assertForbidden();
    }

    public function test_index_only_returns_invoices_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userA, $companyA, ['invoices.view_any']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        Invoice::factory()->create([
            'company_id'  => $companyA->id,
            'customer_id' => $customerA->id,
            'reference'   => 'FAC-MY-00001',
        ]);

        $companyB  = Company::factory()->create();
        $customerB = Customer::factory()->company()->create(['company_id' => $companyB->id]);
        Invoice::factory()->create([
            'company_id'  => $companyB->id,
            'customer_id' => $customerB->id,
            'reference'   => 'FAC-OTHER-00001',
        ]);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/invoices');

        $refs = collect($response->json('data'))->pluck('reference')->toArray();
        $this->assertContains('FAC-MY-00001', $refs);
        $this->assertNotContains('FAC-OTHER-00001', $refs);
    }

    public function test_index_can_filter_by_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.view_any']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        Invoice::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        Invoice::factory()->sent()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/invoices?status=draft');

        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals(['draft'], $statuses);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/invoices', $this->invoicePayload($customer->id));

        $response->assertCreated()
            ->assertJsonFragment(['status' => 'draft'])
            ->assertJsonPath('data.customer_id', $customer->id);

        $this->assertDatabaseHas('invoices', ['company_id' => $company->id, 'customer_id' => $customer->id]);
    }

    public function test_store_auto_generates_reference(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/invoices', $this->invoicePayload($customer->id));

        $response->assertCreated();
        $ref = $response->json('data.reference');
        $this->assertNotNull($ref);
        $this->assertMatchesRegularExpression('/^FAC-\d{4}-\d{5}$/', $ref);
    }

    public function test_store_calculates_line_totals(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = [
            'customer_id'  => $customer->id,
            'invoice_date' => now()->toDateString(),
            'lines'        => [
                [
                    'description'    => 'Product X',
                    'quantity'       => 10,
                    'unit_price_ht'  => 100.00,
                    'discount_type'  => 'percentage',
                    'discount_value' => 10,
                    'tax_rate'       => 20,
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/invoices', $payload);

        $response->assertCreated();
        // subtotal_ht = 10 * 100 = 1000, discount = 100, after_discount = 900, tax = 180, ttc = 1080
        $this->assertEquals('1000.00', $response->json('data.subtotal_ht'));
        $this->assertEquals('100.00', $response->json('data.total_discount'));
        $this->assertEquals('180.00', $response->json('data.total_tax'));
        $this->assertEquals('1080.00', $response->json('data.total_ttc'));
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/invoices', $this->invoicePayload($customer->id))
            ->assertForbidden();
    }

    public function test_store_requires_customer_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.create']);

        $payload = [
            'invoice_date' => now()->toDateString(),
            'lines'        => [['description' => 'X', 'quantity' => 1, 'unit_price_ht' => 100]],
        ];

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/invoices', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_requires_invoice_date(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = [
            'customer_id' => $customer->id,
            'lines'       => [['description' => 'X', 'quantity' => 1, 'unit_price_ht' => 100]],
        ];

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/invoices', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['invoice_date']);
    }

    public function test_store_requires_at_least_one_line(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = [
            'customer_id'  => $customer->id,
            'invoice_date' => now()->toDateString(),
            'lines'        => [],
        ];

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/invoices', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    public function test_store_validates_line_description_required(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = [
            'customer_id'  => $customer->id,
            'invoice_date' => now()->toDateString(),
            'lines'        => [['quantity' => 1, 'unit_price_ht' => 100]],
        ];

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/invoices', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.description']);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/invoices', [])->assertUnauthorized();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.view']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $invoice->id])
            ->assertJsonStructure(['data' => ['id', 'reference', 'status', 'customer_id', 'lines']]);
    }

    public function test_show_returns_404_for_nonexistent_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/invoices/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/invoices/{$invoice->id}")
            ->assertForbidden();
    }

    public function test_show_requires_authentication(): void
    {
        $this->getJson('/api/invoices/1')->assertUnauthorized();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_draft_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/invoices/{$invoice->id}", ['notes' => 'Updated notes'])
            ->assertOk()
            ->assertJsonFragment(['notes' => 'Updated notes']);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/invoices/{$invoice->id}", ['notes' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_cannot_update_sent_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->sent()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/invoices/{$invoice->id}", ['notes' => 'Should fail'])
            ->assertUnprocessable();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_draft_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.delete']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/invoices/{$invoice->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/invoices/{$invoice->id}")
            ->assertForbidden();
    }

    public function test_cannot_delete_sent_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.delete']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->sent()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/invoices/{$invoice->id}")
            ->assertUnprocessable();
    }

    // ─── Lifecycle: Send ──────────────────────────────────────────────────────

    public function test_draft_invoice_can_be_sent(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.send']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/send")
            ->assertOk()
            ->assertJsonFragment(['status' => 'sent']);

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'sent']);
    }

    public function test_sent_invoice_cannot_be_sent_again(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.send']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->sent()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/send")
            ->assertStatus(422);
    }

    public function test_send_requires_send_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/send")
            ->assertForbidden();
    }

    // ─── Lifecycle: Cancel ────────────────────────────────────────────────────

    public function test_draft_invoice_can_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.cancel']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/cancel", ['reason' => 'Customer request'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_sent_invoice_can_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.cancel']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->sent()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/cancel")
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_paid_invoice_cannot_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.cancel']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->paid()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/cancel")
            ->assertStatus(422);
    }

    public function test_already_cancelled_invoice_cannot_be_cancelled_again(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['invoices.cancel']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->cancelled()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/cancel")
            ->assertStatus(422);
    }

    public function test_cancel_requires_cancel_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $invoice  = Invoice::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoice->id}/cancel")
            ->assertForbidden();
    }

    // ─── Full Lifecycle ───────────────────────────────────────────────────────

    public function test_full_lifecycle_draft_to_sent(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $this->allInvoicePermissions());

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        // 1. Create (draft)
        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/invoices', $this->invoicePayload($customer->id))
            ->assertCreated();

        $invoiceId = $createResponse->json('data.id');
        $this->assertEquals('draft', $createResponse->json('data.status'));

        // 2. Send → sent
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoiceId}/send")
            ->assertOk()
            ->assertJsonFragment(['status' => 'sent']);

        // 3. Cancel → cancelled
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/invoices/{$invoiceId}/cancel", ['reason' => 'Test cancellation'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_cannot_view_invoice_from_another_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['invoices.view']);
        $this->giveUserPermissions($userB, $companyB, ['invoices.view']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $invoiceA  = Invoice::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id]);

        $this->assertTenantIsolation('/api/invoices', $userA, $userB, $invoiceA->id);
    }
}
