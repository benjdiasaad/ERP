<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\Supplier;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
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

    private function poPayload(int $supplierId): array
    {
        return [
            'supplier_id' => $supplierId,
            'order_date'  => now()->toDateString(),
            'lines'       => [
                [
                    'description'   => 'Product A',
                    'quantity'      => 5,
                    'unit_price_ht' => 100,
                    'tax_rate'      => 20,
                ],
            ],
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_purchase_orders(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.view_any']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        PurchaseOrder::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/purchase-orders')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/purchase-orders')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/purchase-orders')
            ->assertForbidden();
    }

    public function test_index_only_returns_orders_from_current_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.view_any']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        PurchaseOrder::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id, 'reference' => 'PO-MY']);

        $otherCompany = Company::factory()->create();
        $otherSupplier = Supplier::factory()->create(['company_id' => $otherCompany->id]);
        PurchaseOrder::factory()->create(['company_id' => $otherCompany->id, 'supplier_id' => $otherSupplier->id, 'reference' => 'PO-OTHER']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/purchase-orders');

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference')->toArray();
        $this->assertContains('PO-MY', $refs);
        $this->assertNotContains('PO-OTHER', $refs);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_purchase_order_with_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.create']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-orders', $this->poPayload($supplier->id));

        $response->assertCreated()
            ->assertJsonFragment(['supplier_id' => $supplier->id]);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('purchase_order_lines', ['purchase_order_id' => $id, 'description' => 'Product A']);
    }

    public function test_store_auto_generates_reference_in_po_format(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.create']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-orders', $this->poPayload($supplier->id));

        $response->assertCreated();
        $reference = $response->json('data.reference');
        $this->assertMatchesRegularExpression('/^PO-\d{4}-\d{5}$/', $reference);
    }

    public function test_store_calculates_line_amounts_correctly(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.create']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-orders', $this->poPayload($supplier->id));

        $response->assertCreated();
        // qty=5, price=100 → subtotal=500, tax=20% → tax_amount=100, total_ttc=600
        $this->assertEquals('500.00', $response->json('data.subtotal_ht'));
        $this->assertEquals('600.00', $response->json('data.total_ttc'));
    }

    public function test_store_requires_supplier_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.create']);

        $payload = ['order_date' => now()->toDateString(), 'lines' => [['description' => 'A', 'quantity' => 1, 'unit_price_ht' => 10]]];

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-orders', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_store_requires_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.create']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-orders', ['supplier_id' => $supplier->id, 'order_date' => now()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-orders', $this->poPayload($supplier->id))
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_purchase_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.view']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/purchase-orders/{$order->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $order->id]);
    }

    public function test_show_returns_404_for_nonexistent_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/purchase-orders/99999')
            ->assertNotFound();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_draft_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.update']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/purchase-orders/{$order->id}", ['notes' => 'Updated notes'])
            ->assertOk()
            ->assertJsonFragment(['notes' => 'Updated notes']);
    }

    public function test_update_non_draft_order_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.update']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->sent()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/purchase-orders/{$order->id}", ['notes' => 'Hacked'])
            ->assertUnprocessable();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_delete_draft_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.delete']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/purchase-orders/{$order->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('purchase_orders', ['id' => $order->id]);
    }

    public function test_delete_non_draft_order_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.delete']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->sent()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/purchase-orders/{$order->id}")
            ->assertUnprocessable();
    }

    // ─── Send ─────────────────────────────────────────────────────────────────

    public function test_draft_order_can_be_sent(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.send']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-orders/{$order->id}/send")
            ->assertOk()
            ->assertJsonFragment(['status' => 'sent']);

        $this->assertNotNull($order->fresh()->sent_at);
    }

    public function test_send_non_draft_order_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.send']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->sent()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-orders/{$order->id}/send")
            ->assertUnprocessable();
    }

    public function test_send_requires_send_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-orders/{$order->id}/send")
            ->assertForbidden();
    }

    // ─── Confirm ──────────────────────────────────────────────────────────────

    public function test_sent_order_can_be_confirmed(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.confirm']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->sent()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-orders/{$order->id}/confirm")
            ->assertOk()
            ->assertJsonFragment(['status' => 'confirmed']);

        $fresh = $order->fresh();
        $this->assertNotNull($fresh->confirmed_at);
        $this->assertNotNull($fresh->confirmed_by);
    }

    public function test_confirm_non_sent_order_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.confirm']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-orders/{$order->id}/confirm")
            ->assertUnprocessable();
    }

    public function test_confirm_requires_confirm_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->sent()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-orders/{$order->id}/confirm")
            ->assertForbidden();
    }

    // ─── Cancel ───────────────────────────────────────────────────────────────

    public function test_draft_order_can_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.cancel']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_sent_order_can_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.cancel']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->sent()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_confirmed_order_can_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.cancel']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->confirmed()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_received_order_cannot_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_orders.cancel']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->received()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-orders/{$order->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_cancel_requires_cancel_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order = PurchaseOrder::factory()->draft()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-orders/{$order->id}/cancel")
            ->assertForbidden();
    }

    // ─── Full Lifecycle ───────────────────────────────────────────────────────

    public function test_full_happy_path_draft_send_confirm(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, [
            'purchase_orders.create',
            'purchase_orders.send',
            'purchase_orders.confirm',
        ]);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        // Create
        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-orders', $this->poPayload($supplier->id));
        $createResponse->assertCreated();
        $id = $createResponse->json('data.id');

        // Send
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-orders/{$id}/send")
            ->assertOk()
            ->assertJsonFragment(['status' => 'sent']);

        // Confirm
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-orders/{$id}/confirm")
            ->assertOk()
            ->assertJsonFragment(['status' => 'confirmed']);

        $this->assertDatabaseHas('purchase_orders', ['id' => $id, 'status' => 'confirmed']);
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_order_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['purchase_orders.view']);
        $this->giveUserPermissions($userB, $companyB, ['purchase_orders.view']);

        $supplier = Supplier::factory()->create(['company_id' => $companyA->id]);
        $order = PurchaseOrder::factory()->create(['company_id' => $companyA->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/purchase-orders/{$order->id}")
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_update_order_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['purchase_orders.update']);
        $this->giveUserPermissions($userB, $companyB, ['purchase_orders.update']);

        $supplier = Supplier::factory()->create(['company_id' => $companyA->id]);
        $order = PurchaseOrder::factory()->draft()->create(['company_id' => $companyA->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/purchase-orders/{$order->id}", ['notes' => 'Hijacked'])
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_delete_order_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['purchase_orders.delete']);
        $this->giveUserPermissions($userB, $companyB, ['purchase_orders.delete']);

        $supplier = Supplier::factory()->create(['company_id' => $companyA->id]);
        $order = PurchaseOrder::factory()->draft()->create(['company_id' => $companyA->id, 'supplier_id' => $supplier->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/purchase-orders/{$order->id}")
            ->assertNotFound();
    }
}
