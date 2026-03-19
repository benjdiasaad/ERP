<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Finance\PaymentTerm;
use Tests\TestCase;

class PaymentTermTest extends TestCase
{
    private function giveUserPermissions(User $user, Company $company, array $permissionSlugs): void
    {
        $role = \App\Models\Auth\Role::create([
            'company_id' => $company->id,
            'name' => 'Test Role',
            'slug' => 'test-role-' . uniqid(),
            'is_system' => false,
        ]);

        foreach ($permissionSlugs as $slug) {
            $permission = \App\Models\Auth\Permission::firstOrCreate(
                ['slug' => $slug],
                ['module' => explode('.', $slug)[0], 'name' => $slug, 'description' => '']
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->syncWithoutDetaching([$role->id => ['company_id' => $company->id]]);
    }

    private function paymentTermPayload(string $name = 'Net 30'): array
    {
        return [
            'name'        => $name,
            'days'        => 30,
            'description' => 'Payment due within 30 days',
            'is_active'   => true,
        ];
    }

    private function allPaymentTermPermissions(): array
    {
        return [
            'payment_terms.view_any',
            'payment_terms.view',
            'payment_terms.create',
            'payment_terms.update',
            'payment_terms.delete',
            'payment_terms.restore',
            'payment_terms.force_delete',
            'payment_terms.export',
        ];
    }

    public function test_user_with_permission_can_list_payment_terms(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_terms.view_any']);

        PaymentTerm::factory()->count(3)->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payment-terms');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'name', 'days']]]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/payment-terms');

        $response->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payment-terms');

        $response->assertForbidden();
    }

    public function test_index_only_returns_payment_terms_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['payment_terms.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['payment_terms.view_any']);

        PaymentTerm::factory()->create(['company_id' => $companyA->id, 'name' => 'Net 30']);
        PaymentTerm::factory()->create(['company_id' => $companyB->id, 'name' => 'Net 60']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/payment-terms');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Net 30', $response->json('data.0.name'));
    }

    public function test_user_with_permission_can_create_payment_term(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_terms.create']);

        $payload = $this->paymentTermPayload('Net 60');
        $payload['days'] = 60;

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payment-terms', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Net 60');
        $response->assertJsonPath('data.days', 60);

        $this->assertDatabaseHas('payment_terms', [
            'company_id' => $company->id,
            'name'       => 'Net 60',
            'days'       => 60,
        ]);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payment-terms', $this->paymentTermPayload());

        $response->assertForbidden();
    }

    public function test_store_requires_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_terms.create']);

        $payload = $this->paymentTermPayload();
        unset($payload['name']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payment-terms', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_requires_days(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_terms.create']);

        $payload = $this->paymentTermPayload();
        unset($payload['days']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payment-terms', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('days');
    }

    public function test_store_days_must_be_integer(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_terms.create']);

        $payload = $this->paymentTermPayload();
        $payload['days'] = 'invalid';

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payment-terms', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('days');
    }

    public function test_store_days_must_be_non_negative(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_terms.create']);

        $payload = $this->paymentTermPayload();
        $payload['days'] = -5;

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payment-terms', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('days');
    }

    public function test_user_with_permission_can_view_payment_term(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_terms.view']);

        $paymentTerm = PaymentTerm::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/payment-terms/{$paymentTerm->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $paymentTerm->id);
        $response->assertJsonPath('data.name', $paymentTerm->name);
    }

    public function test_show_returns_404_for_nonexistent_payment_term(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_terms.view']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payment-terms/99999');

        $response->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $paymentTerm = PaymentTerm::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/payment-terms/{$paymentTerm->id}");

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_update_payment_term(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_terms.update']);

        $paymentTerm = PaymentTerm::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/payment-terms/{$paymentTerm->id}", [
                'name'        => 'Net 90',
                'days'        => 90,
                'description' => 'Updated description',
                'is_active'   => false,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Net 90');
        $response->assertJsonPath('data.days', 90);

        $this->assertDatabaseHas('payment_terms', [
            'id'        => $paymentTerm->id,
            'name'      => 'Net 90',
            'days'      => 90,
            'is_active' => false,
        ]);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $paymentTerm = PaymentTerm::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/payment-terms/{$paymentTerm->id}", ['name' => 'Updated']);

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_soft_delete_payment_term(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_terms.delete']);

        $paymentTerm = PaymentTerm::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/payment-terms/{$paymentTerm->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('payment_terms', ['id' => $paymentTerm->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $paymentTerm = PaymentTerm::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/payment-terms/{$paymentTerm->id}");

        $response->assertForbidden();
    }

    public function test_user_from_company_b_cannot_view_payment_term_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['payment_terms.view']);

        $paymentTerm = PaymentTerm::factory()->create(['company_id' => $companyA->id]);

        $this->assertTenantIsolation('/api/payment-terms', $userA, $userB, $paymentTerm->id);
    }

    public function test_user_from_company_b_cannot_update_payment_term_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['payment_terms.update']);

        $paymentTerm = PaymentTerm::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/payment-terms/{$paymentTerm->id}", ['name' => 'Hacked']);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_user_from_company_b_cannot_delete_payment_term_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['payment_terms.delete']);

        $paymentTerm = PaymentTerm::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/payment-terms/{$paymentTerm->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_index_does_not_leak_payment_terms_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['payment_terms.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['payment_terms.view_any']);

        PaymentTerm::factory()->count(2)->create(['company_id' => $companyA->id]);
        PaymentTerm::factory()->count(3)->create(['company_id' => $companyB->id]);

        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/payment-terms');

        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson('/api/payment-terms');

        $responseA->assertOk();
        $responseB->assertOk();
        $this->assertCount(2, $responseA->json('data'));
        $this->assertCount(3, $responseB->json('data'));
    }

    public function test_search_by_name_returns_matching_payment_terms(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_terms.view_any']);

        PaymentTerm::factory()->create(['company_id' => $company->id, 'name' => 'Net 30']);
        PaymentTerm::factory()->create(['company_id' => $company->id, 'name' => 'Net 60']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payment-terms?search=30');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Net 30', $response->json('data.0.name'));
    }

    public function test_filter_by_is_active(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_terms.view_any']);

        PaymentTerm::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        PaymentTerm::factory()->create(['company_id' => $company->id, 'is_active' => false]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payment-terms?is_active=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_active'));
    }
}
