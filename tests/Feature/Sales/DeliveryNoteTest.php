<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Sales\Customer;
use App\Models\Sales\DeliveryNote;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use Tests\TestCase;

class DeliveryNoteTest extends TestCase
{
    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function giveUserPermissions(User $user, Company $company, array $permissionSlugs): void
    {
        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Test Role',
            'slug' => 'test-role-' . uniqid(),
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

    private function deliveryNotePayload(int $customerId): array
    {
        return [
            'customer_id' => $customerId,
            'date'  => now()->toDateString(),
            'lines' => [
                [
                    'description'      => 'Product A',
                    'ordered_quantity' => 5,
                    'shipped_quantity' => 0,
                    'unit'             => 'pcs',
                ],
            ],
        ];
    }

    private function allDeliveryNotePermissions(): array
    {
        return [
            'delivery_notes.view_any', 'delivery_notes.view', 'delivery_notes.create',
            'delivery_notes.update', 'delivery_notes.delete', 'delivery_notes.ship',
            'delivery_notes.deliver',
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_delivery_notes(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.view_any']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        DeliveryNote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/delivery-notes')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/delivery-notes')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/delivery-notes')
            ->assertForbidden();
    }

    public function test_index_only_returns_delivery_notes_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userA, $companyA, ['delivery_notes.view_any']);

        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        DeliveryNote::factory()->create([
            'company_id'  => $companyA->id,
            'customer_id' => $customerA->id,
            'reference'   => 'BL-MY-00001',
        ]);

        $companyB  = Company::factory()->create();
        $customerB = Customer::factory()->company()->create(['company_id' => $companyB->id]);
        DeliveryNote::factory()->create([
            'company_id'  => $companyB->id,
            'customer_id' => $customerB->id,
            'reference'   => 'BL-OTHER-00001',
        ]);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/delivery-notes');

        $refs = collect($response->json('data'))->pluck('reference')->toArray();
        $this->assertContains('BL-MY-00001', $refs);
        $this->assertNotContains('BL-OTHER-00001', $refs);
    }

    public function test_index_can_filter_by_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.view_any']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        DeliveryNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        DeliveryNote::factory()->shipped()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/delivery-notes?status=draft');

        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals(['draft'], $statuses);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_delivery_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/delivery-notes', $this->deliveryNotePayload($customer->id));

        $response->assertCreated()
            ->assertJsonFragment(['status' => 'draft'])
            ->assertJsonPath('data.customer_id', $customer->id);

        $this->assertDatabaseHas('delivery_notes', ['company_id' => $company->id, 'customer_id' => $customer->id]);
    }

    public function test_store_auto_generates_reference(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/delivery-notes', $this->deliveryNotePayload($customer->id));

        $response->assertCreated();
        $ref = $response->json('data.reference');
        $this->assertNotNull($ref);
        $this->assertMatchesRegularExpression('/^BL-\d{4}-\d{5}$/', $ref);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/delivery-notes', $this->deliveryNotePayload($customer->id))
            ->assertForbidden();
    }

    public function test_store_requires_customer_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.create']);

        $payload = [
            'date'  => now()->toDateString(),
            'lines' => [['description' => 'X', 'ordered_quantity' => 1]],
        ];

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/delivery-notes', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_requires_at_least_one_line(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = [
            'customer_id' => $customer->id,
            'date'        => now()->toDateString(),
            'lines'       => [],
        ];

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/delivery-notes', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    public function test_store_can_link_to_sales_order(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order    = SalesOrder::factory()->confirmed()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $payload = array_merge($this->deliveryNotePayload($customer->id), [
            'sales_order_id' => $order->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/delivery-notes', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.sales_order_id', $order->id);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_delivery_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.view']);

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/delivery-notes/{$deliveryNote->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $deliveryNote->id]);
    }

    public function test_show_returns_404_for_nonexistent_delivery_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/delivery-notes/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/delivery-notes/{$deliveryNote->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_draft_delivery_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.update']);

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/delivery-notes/{$deliveryNote->id}", ['notes' => 'Updated notes'])
            ->assertOk()
            ->assertJsonFragment(['notes' => 'Updated notes']);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/delivery-notes/{$deliveryNote->id}", ['notes' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_cannot_update_shipped_delivery_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.update']);

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->shipped()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/delivery-notes/{$deliveryNote->id}", ['notes' => 'Should fail'])
            ->assertUnprocessable();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_draft_delivery_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.delete']);

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/delivery-notes/{$deliveryNote->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('delivery_notes', ['id' => $deliveryNote->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/delivery-notes/{$deliveryNote->id}")
            ->assertForbidden();
    }

    public function test_cannot_delete_shipped_delivery_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.delete']);

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->shipped()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/delivery-notes/{$deliveryNote->id}")
            ->assertUnprocessable();
    }

    // ─── Lifecycle: Ship ──────────────────────────────────────────────────────

    public function test_ready_delivery_note_can_be_shipped(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.ship', 'delivery_notes.update']);

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->ready()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/delivery-notes/{$deliveryNote->id}/ship", [
                'carrier'         => 'DHL',
                'tracking_number' => 'TRACK123',
            ])
            ->assertOk()
            ->assertJsonFragment(['status' => 'shipped']);

        $this->assertDatabaseHas('delivery_notes', ['id' => $deliveryNote->id, 'status' => 'shipped']);
        $this->assertNotNull($deliveryNote->fresh()->shipped_at);
    }

    public function test_ship_stores_carrier_and_tracking_number(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.ship', 'delivery_notes.update']);

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->ready()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/delivery-notes/{$deliveryNote->id}/ship", [
                'carrier'         => 'FedEx',
                'tracking_number' => 'FX-9876543',
            ])
            ->assertOk();

        $this->assertDatabaseHas('delivery_notes', [
            'id'              => $deliveryNote->id,
            'carrier'         => 'FedEx',
            'tracking_number' => 'FX-9876543',
        ]);
    }

    public function test_draft_delivery_note_cannot_be_shipped(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.ship', 'delivery_notes.update']);

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/delivery-notes/{$deliveryNote->id}/ship")
            ->assertUnprocessable();
    }

    public function test_ship_requires_ship_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->ready()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/delivery-notes/{$deliveryNote->id}/ship")
            ->assertForbidden();
    }

    // ─── Lifecycle: Deliver ───────────────────────────────────────────────────

    public function test_shipped_delivery_note_can_be_delivered(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.deliver', 'delivery_notes.update']);

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->shipped()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/delivery-notes/{$deliveryNote->id}/deliver")
            ->assertOk()
            ->assertJsonFragment(['status' => 'delivered']);

        $this->assertDatabaseHas('delivery_notes', ['id' => $deliveryNote->id, 'status' => 'delivered']);
        $this->assertNotNull($deliveryNote->fresh()->delivered_at);
    }

    public function test_deliver_updates_sales_order_line_delivered_quantity(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.deliver', 'delivery_notes.update']);

        $customer  = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order     = SalesOrder::factory()->inProgress()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        $orderLine = SalesOrderLine::factory()->create([
            'company_id'         => $company->id,
            'sales_order_id'     => $order->id,
            'quantity'           => 10,
            'delivered_quantity' => 0,
        ]);

        $deliveryNote = DeliveryNote::factory()->shipped()->create([
            'company_id'     => $company->id,
            'customer_id'    => $customer->id,
            'sales_order_id' => $order->id,
        ]);

        $deliveryNote->lines()->create([
            'sales_order_line_id' => $orderLine->id,
            'description'         => 'Product A',
            'ordered_quantity'    => 10,
            'shipped_quantity'    => 10,
            'sort_order'          => 0,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/delivery-notes/{$deliveryNote->id}/deliver")
            ->assertOk();

        $this->assertEquals('10.0000', $orderLine->fresh()->delivered_quantity);
    }

    public function test_deliver_transitions_sales_order_to_delivered_when_fully_delivered(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.deliver', 'delivery_notes.update']);

        $customer  = Customer::factory()->company()->create(['company_id' => $company->id]);
        $order     = SalesOrder::factory()->inProgress()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);
        $orderLine = SalesOrderLine::factory()->create([
            'company_id'         => $company->id,
            'sales_order_id'     => $order->id,
            'quantity'           => 5,
            'delivered_quantity' => 0,
        ]);

        $deliveryNote = DeliveryNote::factory()->shipped()->create([
            'company_id'     => $company->id,
            'customer_id'    => $customer->id,
            'sales_order_id' => $order->id,
        ]);

        $deliveryNote->lines()->create([
            'sales_order_line_id' => $orderLine->id,
            'description'         => 'Product A',
            'ordered_quantity'    => 5,
            'shipped_quantity'    => 5,
            'sort_order'          => 0,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/delivery-notes/{$deliveryNote->id}/deliver")
            ->assertOk();

        $this->assertDatabaseHas('sales_orders', ['id' => $order->id, 'status' => 'delivered']);
    }

    public function test_draft_delivery_note_cannot_be_delivered(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['delivery_notes.deliver', 'delivery_notes.update']);

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->draft()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/delivery-notes/{$deliveryNote->id}/deliver")
            ->assertUnprocessable();
    }

    public function test_deliver_requires_deliver_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer     = Customer::factory()->company()->create(['company_id' => $company->id]);
        $deliveryNote = DeliveryNote::factory()->shipped()->create(['company_id' => $company->id, 'customer_id' => $customer->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/delivery-notes/{$deliveryNote->id}/deliver")
            ->assertForbidden();
    }

    // ─── Full Lifecycle ───────────────────────────────────────────────────────

    public function test_full_lifecycle_draft_to_delivered(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, $this->allDeliveryNotePermissions());

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        // 1. Create (draft)
        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/delivery-notes', $this->deliveryNotePayload($customer->id))
            ->assertCreated();

        $noteId = $createResponse->json('data.id');
        $this->assertEquals('draft', $createResponse->json('data.status'));

        // 2. Manually transition to ready for ship test
        DeliveryNote::find($noteId)->update(['status' => 'ready']);

        // 3. Ship -> shipped
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/delivery-notes/{$noteId}/ship", ['carrier' => 'DHL'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'shipped']);

        // 4. Deliver -> delivered
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/delivery-notes/{$noteId}/deliver")
            ->assertOk()
            ->assertJsonFragment(['status' => 'delivered']);

        $this->assertDatabaseHas('delivery_notes', ['id' => $noteId, 'status' => 'delivered']);
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_tenant_isolation_delivery_note_not_visible_to_other_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['delivery_notes.view']);

        $customerA    = Customer::factory()->company()->create(['company_id' => $companyA->id]);
        $deliveryNote = DeliveryNote::factory()->create(['company_id' => $companyA->id, 'customer_id' => $customerA->id]);

        $this->assertTenantIsolation('/api/delivery-notes', $userA, $userB, $deliveryNote->id);
    }
}
