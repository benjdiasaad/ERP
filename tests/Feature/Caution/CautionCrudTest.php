<?php

declare(strict_types=1);

namespace Tests\Feature\Caution;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Caution\Caution;
use App\Models\Caution\CautionType;
use App\Models\Company\Company;
use App\Models\Purchasing\Supplier;
use App\Models\Sales\Customer;
use Tests\TestCase;

class CautionCrudTest extends TestCase
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

    private function cautionPayload(int $cautionTypeId, int $partnerId): array
    {
        return [
            'caution_type_id' => $cautionTypeId,
            'direction'       => 'given',
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $partnerId,
            'amount'          => 5000.00,
            'issue_date'      => now()->toDateString(),
            'expiry_date'     => now()->addMonths(6)->toDateString(),
            'bank_name'       => 'Test Bank',
            'bank_account'    => '1234567890',
            'notes'           => 'Test caution',
        ];
    }

    private function allCautionPermissions(): array
    {
        return [
            'cautions.view_any',
            'cautions.view',
            'cautions.create',
            'cautions.update',
            'cautions.delete',
            'cautions.restore',
            'cautions.force_delete',
            'cautions.export',
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_cautions(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.view_any']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        Caution::factory()->count(3)->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/cautions');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'links', 'meta']);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/cautions')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/cautions')
            ->assertForbidden();
    }

    public function test_index_only_returns_cautions_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userA, $companyA, ['cautions.view_any']);

        $cautionTypeA = CautionType::factory()->create(['company_id' => $companyA->id]);
        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);

        Caution::factory()->create([
            'company_id'      => $companyA->id,
            'caution_type_id' => $cautionTypeA->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customerA->id,
            'direction'       => 'given',
        ]);

        $companyB = Company::factory()->create();
        $cautionTypeB = CautionType::factory()->create(['company_id' => $companyB->id]);
        $customerB = Customer::factory()->company()->create(['company_id' => $companyB->id]);

        Caution::factory()->create([
            'company_id'      => $companyB->id,
            'caution_type_id' => $cautionTypeB->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customerB->id,
            'direction'       => 'received',
        ]);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/cautions');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('given', $response->json('data.0.direction'));
    }

    public function test_index_can_filter_by_direction(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.view_any']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        Caution::factory()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
            'direction'       => 'given',
        ]);

        Caution::factory()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
            'direction'       => 'received',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/cautions?direction=given');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('given', $response->json('data.0.direction'));
    }

    public function test_index_can_filter_by_status(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.view_any']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        Caution::factory()->draft()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
        ]);

        Caution::factory()->active()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/cautions?status=draft');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('draft', $response->json('data.0.status'));
    }

    public function test_index_supports_pagination(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.view_any']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        Caution::factory()->count(20)->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/cautions?paginate=10');

        $response->assertOk();
        $this->assertCount(10, $response->json('data'));
        $this->assertNotNull($response->json('links.next'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_caution(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.create']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/cautions', $this->cautionPayload($cautionType->id, $customer->id));

        $response->assertCreated();
        $response->assertJsonFragment(['status' => 'draft']);
        $response->assertJsonPath('data.direction', 'given');

        $this->assertDatabaseHas('cautions', [
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'status'          => 'draft',
        ]);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/cautions', $this->cautionPayload($cautionType->id, $customer->id))
            ->assertForbidden();
    }

    public function test_store_requires_caution_type_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.create']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);
        $payload = $this->cautionPayload(999, $customer->id);
        unset($payload['caution_type_id']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/cautions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['caution_type_id']);
    }

    public function test_store_requires_direction(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.create']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = $this->cautionPayload($cautionType->id, $customer->id);
        unset($payload['direction']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/cautions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['direction']);
    }

    public function test_store_requires_amount(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.create']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = $this->cautionPayload($cautionType->id, $customer->id);
        unset($payload['amount']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/cautions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_store_requires_issued_at(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.create']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = $this->cautionPayload($cautionType->id, $customer->id);
        unset($payload['issue_date']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/cautions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['issue_date']);
    }

    public function test_store_requires_expiry_at_after_issued_at(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.create']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $payload = $this->cautionPayload($cautionType->id, $customer->id);
        $payload['expiry_date'] = now()->subDays(1)->toDateString();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/cautions', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expiry_date']);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_caution(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.view']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $caution = Caution::factory()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/cautions/{$caution->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $caution->id]);
    }

    public function test_show_returns_404_for_nonexistent_caution(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/cautions/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $caution = Caution::factory()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/cautions/{$caution->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_caution(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.update']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $caution = Caution::factory()->draft()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/cautions/{$caution->id}", [
                'amount' => 7500.00,
                'notes'  => 'Updated notes',
            ])
            ->assertOk()
            ->assertJsonFragment(['amount' => '7500.00', 'notes' => 'Updated notes']);

        $this->assertDatabaseHas('cautions', [
            'id'     => $caution->id,
            'amount' => 7500.00,
            'notes'  => 'Updated notes',
        ]);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $caution = Caution::factory()->draft()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/cautions/{$caution->id}", ['notes' => 'Hacked'])
            ->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_caution(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['cautions.delete']);

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $caution = Caution::factory()->draft()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/cautions/{$caution->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('cautions', ['id' => $caution->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $caution = Caution::factory()->draft()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/cautions/{$caution->id}")
            ->assertForbidden();
    }

    // ─── Authorization ────────────────────────────────────────────────────────

    public function test_user_without_permission_cannot_list_cautions(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/cautions')
            ->assertForbidden();
    }

    public function test_user_without_permission_cannot_create_caution(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/cautions', $this->cautionPayload($cautionType->id, $customer->id))
            ->assertForbidden();
    }

    public function test_user_without_permission_cannot_update_caution(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $caution = Caution::factory()->draft()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/cautions/{$caution->id}", ['notes' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_user_without_permission_cannot_delete_caution(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $cautionType = CautionType::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $caution = Caution::factory()->draft()->create([
            'company_id'      => $company->id,
            'caution_type_id' => $cautionType->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customer->id,
        ]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/cautions/{$caution->id}")
            ->assertForbidden();
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_tenant_isolation_caution_not_visible_to_other_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['cautions.view']);

        $cautionTypeA = CautionType::factory()->create(['company_id' => $companyA->id]);
        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);

        $caution = Caution::factory()->create([
            'company_id'      => $companyA->id,
            'caution_type_id' => $cautionTypeA->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customerA->id,
        ]);

        $this->assertTenantIsolation('/api/cautions', $userA, $userB, $caution->id);
    }

    public function test_tenant_isolation_caution_not_updatable_by_other_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['cautions.update']);

        $cautionTypeA = CautionType::factory()->create(['company_id' => $companyA->id]);
        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);

        $caution = Caution::factory()->create([
            'company_id'      => $companyA->id,
            'caution_type_id' => $cautionTypeA->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customerA->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/cautions/{$caution->id}", ['notes' => 'Hacked']);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_tenant_isolation_caution_not_deletable_by_other_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['cautions.delete']);

        $cautionTypeA = CautionType::factory()->create(['company_id' => $companyA->id]);
        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);

        $caution = Caution::factory()->create([
            'company_id'      => $companyA->id,
            'caution_type_id' => $cautionTypeA->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customerA->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/cautions/{$caution->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_index_does_not_leak_cautions_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['cautions.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['cautions.view_any']);

        $cautionTypeA = CautionType::factory()->create(['company_id' => $companyA->id]);
        $customerA = Customer::factory()->company()->create(['company_id' => $companyA->id]);

        $cautionTypeB = CautionType::factory()->create(['company_id' => $companyB->id]);
        $customerB = Customer::factory()->company()->create(['company_id' => $companyB->id]);

        Caution::factory()->count(2)->create([
            'company_id'      => $companyA->id,
            'caution_type_id' => $cautionTypeA->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customerA->id,
        ]);

        Caution::factory()->count(3)->create([
            'company_id'      => $companyB->id,
            'caution_type_id' => $cautionTypeB->id,
            'partner_type' => 'App\Models\Sales\Customer',
            'partner_id'      => $customerB->id,
        ]);

        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/cautions');

        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson('/api/cautions');

        $responseA->assertOk();
        $responseB->assertOk();
        $this->assertCount(2, $responseA->json('data'));
        $this->assertCount(3, $responseB->json('data'));
    }
}
