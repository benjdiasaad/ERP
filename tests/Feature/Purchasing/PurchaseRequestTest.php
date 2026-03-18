<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Purchasing\PurchaseRequest;
use Tests\TestCase;

class PurchaseRequestTest extends TestCase
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

    private function prPayload(string $title = 'Office Supplies'): array
    {
        return [
            'title'    => $title,
            'priority' => 'medium',
            'lines'    => [
                ['description' => 'Item A', 'quantity' => 2, 'estimated_unit_price' => 100],
            ],
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_purchase_requests(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.view_any']);

        PurchaseRequest::factory()->create(['company_id' => $company->id, 'title' => 'Visible PR']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/purchase-requests');

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Visible PR']);
    }

    public function test_index_returns_paginated_results(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.view_any']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/purchase-requests');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total', 'per_page', 'current_page']]);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/purchase-requests')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/purchase-requests')
            ->assertForbidden();
    }

    public function test_index_only_returns_purchase_requests_from_current_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.view_any']);

        PurchaseRequest::factory()->create(['company_id' => $company->id, 'title' => 'My PR']);

        $otherCompany = Company::factory()->create();
        PurchaseRequest::factory()->create(['company_id' => $otherCompany->id, 'title' => 'Other PR']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/purchase-requests');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('My PR', $titles);
        $this->assertNotContains('Other PR', $titles);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_purchase_request_with_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.create']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-requests', $this->prPayload('New PR'));

        $response->assertCreated()
            ->assertJsonFragment(['title' => 'New PR']);

        $this->assertDatabaseHas('purchase_requests', [
            'title'      => 'New PR',
            'company_id' => $company->id,
        ]);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('purchase_request_lines', [
            'purchase_request_id' => $id,
            'description'         => 'Item A',
        ]);
    }

    public function test_store_auto_generates_reference_in_da_format(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.create']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-requests', $this->prPayload());

        $response->assertCreated();
        $reference = $response->json('data.reference');
        $this->assertNotNull($reference);
        $year = now()->format('Y');
        $this->assertMatchesRegularExpression('/^DA-' . $year . '-\d{5}$/', $reference);
    }

    public function test_store_requires_title(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.create']);

        $payload = $this->prPayload();
        unset($payload['title']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-requests', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_requires_lines(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.create']);

        $payload = $this->prPayload();
        unset($payload['lines']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-requests', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lines']);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-requests', $this->prPayload())
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_purchase_request(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.view']);

        $pr = PurchaseRequest::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/purchase-requests/{$pr->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $pr->id]);
    }

    public function test_show_returns_404_for_nonexistent_purchase_request(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/purchase-requests/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $pr = PurchaseRequest::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/purchase-requests/{$pr->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_draft_purchase_request(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.update']);

        $pr = PurchaseRequest::factory()->draft()->create([
            'company_id' => $company->id,
            'title'      => 'Old Title',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/purchase-requests/{$pr->id}", ['title' => 'Updated Title']);

        $response->assertOk()
            ->assertJsonFragment(['title' => 'Updated Title']);

        $this->assertDatabaseHas('purchase_requests', ['id' => $pr->id, 'title' => 'Updated Title']);
    }

    public function test_update_non_draft_purchase_request_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.update']);

        $pr = PurchaseRequest::factory()->submitted()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/purchase-requests/{$pr->id}", ['title' => 'Hacked'])
            ->assertUnprocessable();
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $pr = PurchaseRequest::factory()->draft()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/purchase-requests/{$pr->id}", ['title' => 'Hacked'])
            ->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_delete_draft_purchase_request(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.delete']);

        $pr = PurchaseRequest::factory()->draft()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/purchase-requests/{$pr->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('purchase_requests', ['id' => $pr->id]);
    }

    public function test_user_with_permission_can_delete_cancelled_purchase_request(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.delete']);

        $pr = PurchaseRequest::factory()->cancelled()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/purchase-requests/{$pr->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('purchase_requests', ['id' => $pr->id]);
    }

    public function test_destroy_non_draft_non_cancelled_purchase_request_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.delete']);

        $pr = PurchaseRequest::factory()->submitted()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/purchase-requests/{$pr->id}")
            ->assertUnprocessable();
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $pr = PurchaseRequest::factory()->draft()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/purchase-requests/{$pr->id}")
            ->assertForbidden();
    }

    // ─── Submit ───────────────────────────────────────────────────────────────

    public function test_draft_purchase_request_can_be_submitted(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.submit']);

        $pr = PurchaseRequest::factory()->draft()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/submit");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'submitted']);

        $this->assertDatabaseHas('purchase_requests', [
            'id'     => $pr->id,
            'status' => 'submitted',
        ]);

        $this->assertNotNull($pr->fresh()->submitted_at);
    }

    public function test_submit_non_draft_purchase_request_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.submit']);

        $pr = PurchaseRequest::factory()->submitted()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/submit")
            ->assertUnprocessable();
    }

    public function test_submit_requires_submit_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $pr = PurchaseRequest::factory()->draft()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/submit")
            ->assertForbidden();
    }

    // ─── Approve ──────────────────────────────────────────────────────────────

    public function test_submitted_purchase_request_can_be_approved(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.approve']);

        $pr = PurchaseRequest::factory()->submitted()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/approve");

        $response->assertOk()
            ->assertJsonFragment(['status' => 'approved']);

        $fresh = $pr->fresh();
        $this->assertNotNull($fresh->approved_at);
        $this->assertNotNull($fresh->approved_by);
    }

    public function test_approve_non_submitted_purchase_request_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.approve']);

        $pr = PurchaseRequest::factory()->draft()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/approve")
            ->assertUnprocessable();
    }

    public function test_approve_requires_approve_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $pr = PurchaseRequest::factory()->submitted()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/approve")
            ->assertForbidden();
    }

    // ─── Reject ───────────────────────────────────────────────────────────────

    public function test_submitted_purchase_request_can_be_rejected(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.reject']);

        $pr = PurchaseRequest::factory()->submitted()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/reject", [
                'rejection_reason' => 'Budget exceeded',
            ]);

        $response->assertOk()
            ->assertJsonFragment(['status' => 'rejected']);

        $fresh = $pr->fresh();
        $this->assertNotNull($fresh->rejected_at);
        $this->assertEquals('Budget exceeded', $fresh->rejection_reason);
    }

    public function test_reject_non_submitted_purchase_request_fails_with_422(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.reject']);

        $pr = PurchaseRequest::factory()->draft()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/reject")
            ->assertUnprocessable();
    }

    public function test_reject_requires_reject_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $pr = PurchaseRequest::factory()->submitted()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/reject")
            ->assertForbidden();
    }

    // ─── Cancel ───────────────────────────────────────────────────────────────

    public function test_draft_purchase_request_can_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.cancel']);

        $pr = PurchaseRequest::factory()->draft()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/cancel")
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_submitted_purchase_request_can_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.cancel']);

        $pr = PurchaseRequest::factory()->submitted()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/cancel")
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_approved_purchase_request_can_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.cancel']);

        $pr = PurchaseRequest::factory()->approved()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/cancel")
            ->assertOk()
            ->assertJsonFragment(['status' => 'cancelled']);
    }

    public function test_rejected_purchase_request_cannot_be_cancelled(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['purchase_requests.cancel']);

        $pr = PurchaseRequest::factory()->rejected()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_cancel_requires_cancel_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $pr = PurchaseRequest::factory()->draft()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$pr->id}/cancel")
            ->assertForbidden();
    }

    // ─── Full Lifecycle ───────────────────────────────────────────────────────

    public function test_full_happy_path_draft_submit_approve(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, [
            'purchase_requests.create',
            'purchase_requests.submit',
            'purchase_requests.approve',
        ]);

        // Create
        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-requests', $this->prPayload('Full Lifecycle PR'));
        $createResponse->assertCreated();
        $id = $createResponse->json('data.id');

        // Submit
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$id}/submit")
            ->assertOk()
            ->assertJsonFragment(['status' => 'submitted']);

        // Approve
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$id}/approve")
            ->assertOk()
            ->assertJsonFragment(['status' => 'approved']);

        $this->assertDatabaseHas('purchase_requests', ['id' => $id, 'status' => 'approved']);
    }

    public function test_rejection_path_draft_submit_reject(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, [
            'purchase_requests.create',
            'purchase_requests.submit',
            'purchase_requests.reject',
        ]);

        // Create
        $createResponse = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/purchase-requests', $this->prPayload('Rejection Path PR'));
        $createResponse->assertCreated();
        $id = $createResponse->json('data.id');

        // Submit
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$id}/submit")
            ->assertOk()
            ->assertJsonFragment(['status' => 'submitted']);

        // Reject
        $this->withHeaders($this->authHeaders($user))
            ->postJson("/api/purchase-requests/{$id}/reject", ['rejection_reason' => 'Not in budget'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'rejected']);

        $this->assertDatabaseHas('purchase_requests', ['id' => $id, 'status' => 'rejected']);
    }

    // ─── Convert to Order (skipped — PurchaseOrder model not yet implemented) ─

    public function test_convert_to_order_is_skipped_until_purchase_order_exists(): void
    {
        // PurchaseOrder model does not exist yet.
        // This test will be implemented once App\Models\Purchasing\PurchaseOrder is available.
        $this->markTestSkipped('PurchaseOrder model not yet implemented.');
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_purchase_request_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['purchase_requests.view']);
        $this->giveUserPermissions($userB, $companyB, ['purchase_requests.view']);

        $pr = PurchaseRequest::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/purchase-requests/{$pr->id}")
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_update_purchase_request_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['purchase_requests.update']);
        $this->giveUserPermissions($userB, $companyB, ['purchase_requests.update']);

        $pr = PurchaseRequest::factory()->draft()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/purchase-requests/{$pr->id}", ['title' => 'Hijacked'])
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_delete_purchase_request_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['purchase_requests.delete']);
        $this->giveUserPermissions($userB, $companyB, ['purchase_requests.delete']);

        $pr = PurchaseRequest::factory()->draft()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/purchase-requests/{$pr->id}")
            ->assertNotFound();
    }
}
