<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderLine;
use App\Models\Purchasing\ReceptionNote;
use App\Models\Purchasing\Supplier;
use Tests\TestCase;

class ReceptionNoteTest extends TestCase
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

    private function makeOrder(int $companyId): PurchaseOrder
    {
        $supplier = Supplier::factory()->create(['company_id' => $companyId]);

        return PurchaseOrder::factory()->confirmed()->create([
            'company_id'  => $companyId,
            'supplier_id' => $supplier->id,
        ]);
    }

    private function rnPayload(int $purchaseOrderId, int $supplierId): array
    {
        return [
            'purchase_order_id' => $purchaseOrderId,
            'supplier_id'       => $supplierId,
            'reception_date'    => now()->toDateString(),
            'lines'             => [
                [
                    'description'      => 'Item A',
                    'ordered_quantity'  => 10,
                    'received_quantity' => 10,
                    'rejected_quantity' => 0,
                ],
            ],
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_reception_notes(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.view_any']);

        $order = $this->makeOrder($company->id);
        ReceptionNote::factory()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/reception-notes')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/reception-notes')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/reception-notes')
            ->assertForbidden();
    }

    public function test_index_only_returns_notes_from_current_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.view_any']);

        $order = $this->makeOrder($company->id);
        ReceptionNote::factory()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id, 'reference' => 'BR-MY']);

        $otherCompany = Company::factory()->create();
        $otherOrder   = $this->makeOrder($otherCompany->id);
        ReceptionNote::factory()->create(['company_id' => $otherCompany->id, 'purchase_order_id' => $otherOrder->id, 'supplier_id' => $otherOrder->supplier_id, 'reference' => 'BR-OTHER']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/reception-notes');

        $response->assertOk();
        $refs = collect($response->json('data'))->pluck('reference')->toArray();
        $this->assertContains('BR-MY', $refs);
        $this->assertNotContains('BR-OTHER', $refs);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_reception_note_with_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.create']);

        $order = $this->makeOrder($company->id);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/reception-notes', $this->rnPayload($order->id, $order->supplier_id));

        $response->assertCreated()
            ->assertJsonFragment(['purchase_order_id' => $order->id]);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('reception_note_lines', ['reception_note_id' => $id, 'description' => 'Item A']);
    }

    public function test_store_auto_generates_reference_in_br_format(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.create']);

        $order = $this->makeOrder($company->id);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/reception-notes', $this->rnPayload($order->id, $order->supplier_id));

        $response->assertCreated();
        $this->assertMatchesRegularExpression('/^BR-\d{4}-\d{5}$/', $response->json('data.reference'));
    }

    public function test_store_requires_purchase_order_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/reception-notes', ['reception_date' => now()->toDateString(), 'lines' => [['description' => 'A', 'ordered_quantity' => 1, 'received_quantity' => 1]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['purchase_order_id']);
    }

    public function test_store_requires_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.create']);

        $order = $this->makeOrder($company->id);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/reception-notes', ['purchase_order_id' => $order->id, 'reception_date' => now()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $order = $this->makeOrder($company->id);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/reception-notes', $this->rnPayload($order->id, $order->supplier_id))
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_reception_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.view']);

        $order = $this->makeOrder($company->id);
        $note  = ReceptionNote::factory()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/reception-notes/{$note->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $note->id]);
    }

    public function test_show_returns_404_for_nonexistent_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/reception-notes/99999')
            ->assertNotFound();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_draft_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.update']);

        $order = $this->makeOrder($company->id);
        $note  = ReceptionNote::factory()->draft()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/reception-notes/{$note->id}", ['notes' => 'Updated notes'])
            ->assertOk()
            ->assertJsonFragment(['notes' => 'Updated notes']);
    }

    public function test_update_confirmed_note_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.update']);

        $order = $this->makeOrder($company->id);
        $note  = ReceptionNote::factory()->confirmed()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/reception-notes/{$note->id}", ['notes' => 'Hacked'])
            ->assertUnprocessable();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_delete_draft_note(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.delete']);

        $order = $this->makeOrder($company->id);
        $note  = ReceptionNote::factory()->draft()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/reception-notes/{$note->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('reception_notes', ['id' => $note->id]);
    }

    public function test_delete_confirmed_note_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.delete']);

        $order = $this->makeOrder($company->id);
        $note  = ReceptionNote::factory()->confirmed()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/reception-notes/{$note->id}")
            ->assertUnprocessable();
    }

    // ─── Confirm ──────────────────────────────────────────────────────────────

    public function test_draft_note_can_be_confirmed(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.confirm']);

        $order = $this->makeOrder($company->id);
        $note  = ReceptionNote::factory()->draft()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/reception-notes/{$note->id}/confirm")
            ->assertOk()
            ->assertJsonFragment(['status' => 'confirmed']);

        $fresh = $note->fresh();
        $this->assertNotNull($fresh->confirmed_at);
        $this->assertNotNull($fresh->confirmed_by);
    }

    public function test_confirm_already_confirmed_note_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.confirm']);

        $order = $this->makeOrder($company->id);
        $note  = ReceptionNote::factory()->confirmed()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/reception-notes/{$note->id}/confirm")
            ->assertUnprocessable();
    }

    public function test_confirm_requires_confirm_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $order = $this->makeOrder($company->id);
        $note  = ReceptionNote::factory()->draft()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/reception-notes/{$note->id}/confirm")
            ->assertForbidden();
    }

    // ─── Confirm updates PO line received_quantity ────────────────────────────

    public function test_confirm_updates_purchase_order_line_received_quantity(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.create', 'reception_notes.confirm']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order    = PurchaseOrder::factory()->confirmed()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);
        $orderLine = PurchaseOrderLine::factory()->create([
            'company_id'        => $company->id,
            'purchase_order_id' => $order->id,
            'description'       => 'Widget',
            'quantity'          => 10,
            'unit_price_ht'     => 50,
            'received_quantity' => 0,
        ]);

        // Create reception note with a line linked to the order line
        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/reception-notes', [
                'purchase_order_id' => $order->id,
                'supplier_id'       => $supplier->id,
                'reception_date'    => now()->toDateString(),
                'lines'             => [
                    [
                        'purchase_order_line_id' => $orderLine->id,
                        'description'            => 'Widget',
                        'ordered_quantity'        => 10,
                        'received_quantity'       => 8,
                        'rejected_quantity'       => 0,
                    ],
                ],
            ]);

        $createResponse->assertCreated();
        $noteId = $createResponse->json('data.id');

        // Confirm the reception note
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/reception-notes/{$noteId}/confirm")
            ->assertOk()
            ->assertJsonFragment(['status' => 'confirmed']);

        // The order line's received_quantity should now be 8
        $this->assertDatabaseHas('purchase_order_lines', [
            'id'                => $orderLine->id,
            'received_quantity' => '8.0000',
        ]);
    }

    public function test_confirm_transitions_purchase_order_to_in_progress(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.create', 'reception_notes.confirm']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);
        $order    = PurchaseOrder::factory()->confirmed()->create(['company_id' => $company->id, 'supplier_id' => $supplier->id]);

        $note = ReceptionNote::factory()->draft()->create([
            'company_id'        => $company->id,
            'purchase_order_id' => $order->id,
            'supplier_id'       => $supplier->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/reception-notes/{$note->id}/confirm")
            ->assertOk();

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id, 'status' => 'in_progress']);
    }

    // ─── Cancel ───────────────────────────────────────────────────────────────

    public function test_draft_note_can_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.cancel']);

        $order = $this->makeOrder($company->id);
        $note  = ReceptionNote::factory()->draft()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/reception-notes/{$note->id}/cancel")
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_confirmed_note_cannot_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['reception_notes.cancel']);

        $order = $this->makeOrder($company->id);
        $note  = ReceptionNote::factory()->confirmed()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/reception-notes/{$note->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_cancel_requires_cancel_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $order = $this->makeOrder($company->id);
        $note  = ReceptionNote::factory()->draft()->create(['company_id' => $company->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/reception-notes/{$note->id}/cancel")
            ->assertForbidden();
    }

    // ─── Full Lifecycle ───────────────────────────────────────────────────────

    public function test_full_lifecycle_create_confirm(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, [
            'reception_notes.create',
            'reception_notes.confirm',
        ]);

        $order = $this->makeOrder($company->id);

        // Create
        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/reception-notes', $this->rnPayload($order->id, $order->supplier_id));
        $createResponse->assertCreated();
        $id = $createResponse->json('data.id');

        // Confirm
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/reception-notes/{$id}/confirm")
            ->assertOk()
            ->assertJsonFragment(['status' => 'confirmed']);

        $this->assertDatabaseHas('reception_notes', ['id' => $id, 'status' => 'confirmed']);
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_note_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['reception_notes.view']);
        $this->giveUserPermissions($userB, $companyB, ['reception_notes.view']);

        $order = $this->makeOrder($companyA->id);
        $note  = ReceptionNote::factory()->create(['company_id' => $companyA->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/reception-notes/{$note->id}")
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_delete_note_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['reception_notes.delete']);
        $this->giveUserPermissions($userB, $companyB, ['reception_notes.delete']);

        $order = $this->makeOrder($companyA->id);
        $note  = ReceptionNote::factory()->draft()->create(['company_id' => $companyA->id, 'purchase_order_id' => $order->id, 'supplier_id' => $order->supplier_id]);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/reception-notes/{$note->id}")
            ->assertNotFound();
    }
}
