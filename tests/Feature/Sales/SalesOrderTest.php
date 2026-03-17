<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Sales\Customer;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use Tests\TestCase;

class SalesOrderTest extends TestCase
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

    private function orderPayload(int $customerId): array
    {
        return [
            'customer_id' => $customerId,
            'order_date'  => now()->toDateString(),
            'lines'       => [
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

    private function allOrderPermissions(): array
    {
        return [
            'sales_orders.view_any', 'sales_orders.view', 'sales_orders.create',
            'sales_orders.update', 'sales_orders.delete', 'sales_orders.confirm',
            'sales_orders.cancel', 'sales_orders.generate_invoice',
            'sales_orders.generate_delivery_note', 'sales_orders.print',
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_sales_orders(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.view_any']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        SalesOrder::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/sales-orders')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/sales-orders')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/sales-orders')
            ->assertForbidden();
    }

    public function test_index_only_returns_orders_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userA, $companyA, ['sales_orders.view_any']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        SalesOrder::factory()->create([
            'company_id'  => $companyA->id,
            'customer_id' => $customerA->id,
            'reference'   => 'BC-MY-00001',
        ]);

        $companyB  = Company::factory()->create();
        $customerB = Customer::factory()->company()->create(['company_id' => $companyB->id]);
        SalesOrder::factory()->create([
            'company_id'  => $companyB->id,
            'customer_id' => $customerB->id,
            'reference'   => 'BC-OTHER-00001',
        ]);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/sales-orders');

        $refs = collect($response->json('data'))->pluck('reference')->toArray();
        $this->assertContains('BC-MY-00001', $refs);
        $this->assertNotContains('BC-OTHER-00001', $refs);
    }

    public function test_index_can_filter_by_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.view_any']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        SalesOrder::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        SalesOrder::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/sales-orders?status=draft');

        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals(['draft'], $statuses);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_sales_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/sales-orders', $this->orderPayload($customer->id));

        $response->assertCreated()
            ->assertJsonFragment(['status' => 'draft'])
            ->assertJsonPath('data.customer_id', $customer->id);

        $this->assertDatabaseHas('sales_orders', ['company_id' => $company->id, 'customer_id' => $customer->id]);
    }

    public function test_store_auto_generates_reference(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/sales-orders', $this->orderPayload($customer->id));

        $response->assertCreated();
        $ref = $response->json('data.reference');
        $this->assertNotNull($ref);
        $this->assertMatchesRegularExpression('/^BC-\d{4}-\d{5}$/', $ref);
    }

    public function test_store_calculates_line_totals(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = [
            'customer_id' => $customer->id,
            'order_date'  => now()->toDateString(),
            'lines'       => [
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
            ->postJson('/api/sales-orders', $payload);

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
            ->postJson('/api/sales-orders', $this->orderPayload($customer->id))
            ->assertForbidden();
    }

    public function test_store_requires_customer_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.create']);

        $payload = [
            'order_date' => now()->toDateString(),
            'lines'      => [['description' => 'X', 'quantity' => 1, 'unit_price_ht' => 100]],
        ];

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/sales-orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_requires_at_least_one_line(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = [
            'customer_id' => $customer->id,
            'order_date'  => now()->toDateString(),
            'lines'       => [],
        ];

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/sales-orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_sales_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.view']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/sales-orders/{$order->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $order->id]);
    }

    public function test_show_returns_404_for_nonexistent_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/sales-orders/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/sales-orders/{$order->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_draft_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/sales-orders/{$order->id}", ['notes' => 'Updated notes'])
            ->assertOk()
            ->assertJsonFragment(['notes' => 'Updated notes']);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/sales-orders/{$order->id}", ['notes' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_cannot_update_confirmed_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/sales-orders/{$order->id}", ['notes' => 'Should fail'])
            ->assertUnprocessable();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_draft_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.delete']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/sales-orders/{$order->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('sales_orders', ['id' => $order->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/sales-orders/{$order->id}")
            ->assertForbidden();
    }

    public function test_cannot_delete_confirmed_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.delete']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/sales-orders/{$order->id}")
            ->assertUnprocessable();
    }

    // ─── Lifecycle: Confirm ───────────────────────────────────────────────────

    public function test_draft_order_can_be_confirmed(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.confirm']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/confirm")
            ->assertOk()
            ->assertJsonFragment(['status' => 'confirmed']);

        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'confirmed']);
        $this->assertNotNull($order->fresh()->confirmed_at);
    }

    public function test_confirmed_order_cannot_be_confirmed_again(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.confirm']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/confirm")
            ->assertStatus(422);
    }

    public function test_confirm_requires_confirm_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/confirm")
            ->assertForbidden();
    }

    // ─── Lifecycle: Cancel ────────────────────────────────────────────────────

    public function test_draft_order_can_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.cancel']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/cancel", ['cancellation_reason' => 'Customer request'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled', 'cancellation_reason' => 'Customer request']);

        $this->assertNotNull($order->fresh()->cancelled_at);
    }

    public function test_confirmed_order_can_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.cancel']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_delivered_order_cannot_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.cancel']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->delivered()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/cancel")
            ->assertStatus(422);
    }

    public function test_invoiced_order_cannot_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.cancel']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->invoiced()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/cancel")
            ->assertStatus(422);
    }

    public function test_cancel_requires_cancel_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/cancel")
            ->assertForbidden();
    }

    // ─── Generate Invoice ─────────────────────────────────────────────────────

    public function test_confirmed_order_can_generate_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.generate_invoice']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        SalesOrderLine::factory()->create(['company_id' => $company->id, 'sales_order_id' => $order->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/generate-invoice");

        $response->assertCreated()
            ->assertJsonStructure(['message', 'invoice']);

        $this->assertDatabaseHas('invoices', ['sales_order_id' => $order->id]);
    }

    public function test_draft_order_cannot_generate_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.generate_invoice']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/generate-invoice")
            ->assertUnprocessable();
    }

    public function test_cancelled_order_cannot_generate_invoice(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.generate_invoice']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->cancelled()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/generate-invoice")
            ->assertUnprocessable();
    }

    public function test_generate_invoice_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/generate-invoice")
            ->assertForbidden();
    }

    public function test_generate_invoice_updates_invoiced_quantity_on_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.generate_invoice']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        $line = SalesOrderLine::factory()->create([
            'company_id'       => $company->id,
            'sales_order_id'   => $order->id,
            'quantity'         => 5,
            'invoiced_quantity' => 0,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/generate-invoice")
            ->assertCreated();

        $this->assertEquals('5.0000', $line->fresh()->invoiced_quantity);
    }

    // ─── Generate Delivery Note ───────────────────────────────────────────────

    public function test_confirmed_order_can_generate_delivery_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.generate_delivery_note']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        SalesOrderLine::factory()->create(['company_id' => $company->id, 'sales_order_id' => $order->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/generate-delivery-note");

        $response->assertCreated()
            ->assertJsonStructure(['message', 'delivery_note']);

        $this->assertDatabaseHas('delivery_notes', ['sales_order_id' => $order->id]);
    }

    public function test_draft_order_cannot_generate_delivery_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.generate_delivery_note']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/generate-delivery-note")
            ->assertUnprocessable();
    }

    public function test_generate_delivery_note_requires_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/generate-delivery-note")
            ->assertForbidden();
    }

    public function test_generate_delivery_note_transitions_order_to_in_progress(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.generate_delivery_note']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        SalesOrderLine::factory()->create(['company_id' => $company->id, 'sales_order_id' => $order->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/generate-delivery-note")
            ->assertCreated();

        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'in_progress']);
    }

    public function test_generate_delivery_note_updates_delivered_quantity_on_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.generate_delivery_note']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        $line = SalesOrderLine::factory()->create([
            'company_id'         => $company->id,
            'sales_order_id'     => $order->id,
            'quantity'           => 3,
            'delivered_quantity' => 0,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$order->id}/generate-delivery-note")
            ->assertCreated();

        $this->assertEquals('3.0000', $line->fresh()->delivered_quantity);
    }

    // ─── Full Lifecycle ───────────────────────────────────────────────────────

    public function test_full_lifecycle_draft_to_invoiced(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $this->allOrderPermissions());

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        // 1. Create (draft)
        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/sales-orders', $this->orderPayload($customer->id))
            ->assertCreated();

        $orderId = $createResponse->json('data.id');
        $this->assertEquals('draft', $createResponse->json('data.status'));

        // 2. Confirm → confirmed
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$orderId}/confirm")
            ->assertOk()
            ->assertJsonFragment(['status' => 'confirmed']);

        // 3. Generate delivery note → in_progress
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$orderId}/generate-delivery-note")
            ->assertCreated();

        $this->assertDatabaseHas('sales_orders', ['id' => $orderId, 'status' => 'in_progress']);

        // 4. Generate invoice (from in_progress)
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$orderId}/generate-invoice")
            ->assertCreated();

        $this->assertDatabaseHas('invoices', ['sales_order_id' => $orderId]);
    }

    public function test_full_lifecycle_draft_to_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $this->allOrderPermissions());

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/sales-orders', $this->orderPayload($customer->id))
            ->assertCreated();

        $orderId = $createResponse->json('data.id');

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$orderId}/confirm")
            ->assertOk();

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/sales-orders/{$orderId}/cancel", ['cancellation_reason' => 'Budget cut'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled', 'cancellation_reason' => 'Budget cut']);
    }

    // ─── Line Calculations ────────────────────────────────────────────────────

    public function test_line_calculation_with_fixed_discount(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = [
            'customer_id' => $customer->id,
            'order_date'  => now()->toDateString(),
            'lines'       => [
                [
                    'description'    => 'Product Y',
                    'quantity'       => 5,
                    'unit_price_ht'  => 200.00,
                    'discount_type'  => 'fixed',
                    'discount_value' => 50,
                    'tax_rate'       => 20,
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/sales-orders', $payload);

        $response->assertCreated();
        // subtotal_ht = 5 * 200 = 1000, discount = 50 (fixed), after_discount = 950, tax = 190, ttc = 1140
        $this->assertEquals('1000.00', $response->json('data.subtotal_ht'));
        $this->assertEquals('50.00', $response->json('data.total_discount'));
        $this->assertEquals('190.00', $response->json('data.total_tax'));
        $this->assertEquals('1140.00', $response->json('data.total_ttc'));
    }

    public function test_line_calculation_with_no_discount_no_tax(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = [
            'customer_id' => $customer->id,
            'order_date'  => now()->toDateString(),
            'lines'       => [
                [
                    'description'    => 'Simple item',
                    'quantity'       => 4,
                    'unit_price_ht'  => 250.00,
                    'discount_type'  => null,
                    'discount_value' => 0,
                    'tax_rate'       => 0,
                ],
            ],
        ];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/sales-orders', $payload);

        $response->assertCreated();
        // subtotal_ht = 4 * 250 = 1000, no discount, no tax, ttc = 1000
        $this->assertEquals('1000.00', $response->json('data.subtotal_ht'));
        $this->assertEquals('0.00', $response->json('data.total_discount'));
        $this->assertEquals('0.00', $response->json('data.total_tax'));
        $this->assertEquals('1000.00', $response->json('data.total_ttc'));
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_order_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['sales_orders.view']);
        $this->giveUserPermissions($userB, $companyB, ['sales_orders.view']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $order = SalesOrder::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/sales-orders/{$order->id}")
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_update_order_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['sales_orders.update']);
        $this->giveUserPermissions($userB, $companyB, ['sales_orders.update']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $order = SalesOrder::factory()->draft()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/sales-orders/{$order->id}", ['notes' => 'Hijacked'])
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_delete_order_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['sales_orders.delete']);
        $this->giveUserPermissions($userB, $companyB, ['sales_orders.delete']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $order = SalesOrder::factory()->draft()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/sales-orders/{$order->id}")
            ->assertNotFound();
    }

    public function test_index_does_not_leak_orders_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['sales_orders.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['sales_orders.view_any']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $customerB = Customer::factory()->company()->create(['company_id' => $companyB->id]);

        SalesOrder::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id, 'reference' => 'BC-A-00001']);
        SalesOrder::factory()->create(['company_id' => $companyB->id, 'customer_id' => $customerB->id, 'reference' => 'BC-B-00001']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/sales-orders');

        $refs = collect($response->json('data'))->pluck('reference')->toArray();
        $this->assertContains('BC-A-00001', $refs);
        $this->assertNotContains('BC-B-00001', $refs);
    }

    public function test_user_from_company_b_cannot_confirm_order_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['sales_orders.confirm']);
        $this->giveUserPermissions($userB, $companyB, ['sales_orders.confirm']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $order = SalesOrder::factory()->draft()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->postJson("/api/sales-orders/{$order->id}/confirm")
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_generate_invoice_for_order_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['sales_orders.generate_invoice']);
        $this->giveUserPermissions($userB, $companyB, ['sales_orders.generate_invoice']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $order = SalesOrder::factory()->confirmed()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->postJson("/api/sales-orders/{$order->id}/generate-invoice")
            ->assertNotFound();
    }

    // ─── PDF ──────────────────────────────────────────────────────────────────

    public function test_user_with_view_permission_can_access_pdf_endpoint(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['sales_orders.view']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/sales-orders/{$order->id}/pdf")
            ->assertStatus(501); // Not yet implemented
    }

    public function test_pdf_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order = SalesOrder::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/sales-orders/{$order->id}/pdf")
            ->assertForbidden();
    }
}
