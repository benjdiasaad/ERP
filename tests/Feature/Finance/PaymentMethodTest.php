<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Finance\PaymentMethod;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
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

    private function paymentMethodPayload(string $name = 'Bank Transfer'): array
    {
        return [
            'name'        => $name,
            'type'        => 'bank_transfer',
            'description' => 'Direct bank transfer',
            'is_active'   => true,
        ];
    }

    private function allPaymentMethodPermissions(): array
    {
        return [
            'payment_methods.view_any',
            'payment_methods.view',
            'payment_methods.create',
            'payment_methods.update',
            'payment_methods.delete',
            'payment_methods.restore',
            'payment_methods.force_delete',
            'payment_methods.export',
        ];
    }

    public function test_user_with_permission_can_list_payment_methods(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_methods.view_any']);

        PaymentMethod::factory()->count(3)->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payment-methods');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'name', 'type']]]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/payment-methods');

        $response->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payment-methods');

        $response->assertForbidden();
    }

    public function test_index_only_returns_payment_methods_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['payment_methods.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['payment_methods.view_any']);

        PaymentMethod::factory()->create(['company_id' => $companyA->id, 'name' => 'Cash']);
        PaymentMethod::factory()->create(['company_id' => $companyB->id, 'name' => 'Check']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/payment-methods');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Cash', $response->json('data.0.name'));
    }

    public function test_user_with_permission_can_create_payment_method(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_methods.create']);

        $payload = $this->paymentMethodPayload('Credit Card');
        $payload['type'] = 'credit_card';

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payment-methods', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Credit Card');
        $response->assertJsonPath('data.type', 'credit_card');

        $this->assertDatabaseHas('payment_methods', [
            'company_id' => $company->id,
            'name'       => 'Credit Card',
            'type'       => 'credit_card',
        ]);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payment-methods', $this->paymentMethodPayload());

        $response->assertForbidden();
    }

    public function test_store_requires_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_methods.create']);

        $payload = $this->paymentMethodPayload();
        unset($payload['name']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payment-methods', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_requires_type(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_methods.create']);

        $payload = $this->paymentMethodPayload();
        unset($payload['type']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payment-methods', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('type');
    }

    public function test_store_type_must_be_valid(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_methods.create']);

        $payload = $this->paymentMethodPayload();
        $payload['type'] = 'invalid_type';

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/payment-methods', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('type');
    }

    public function test_user_with_permission_can_view_payment_method(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_methods.view']);

        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/payment-methods/{$paymentMethod->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $paymentMethod->id);
        $response->assertJsonPath('data.name', $paymentMethod->name);
    }

    public function test_show_returns_404_for_nonexistent_payment_method(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_methods.view']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payment-methods/99999');

        $response->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/payment-methods/{$paymentMethod->id}");

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_update_payment_method(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_methods.update']);

        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/payment-methods/{$paymentMethod->id}", [
                'name'        => 'Mobile Payment',
                'type'        => 'mobile',
                'description' => 'Updated description',
                'is_active'   => false,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Mobile Payment');
        $response->assertJsonPath('data.type', 'mobile');

        $this->assertDatabaseHas('payment_methods', [
            'id'        => $paymentMethod->id,
            'name'      => 'Mobile Payment',
            'type'      => 'mobile_money',
            'is_active' => false,
        ]);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/payment-methods/{$paymentMethod->id}", ['name' => 'Updated']);

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_soft_delete_payment_method(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_methods.delete']);

        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/payment-methods/{$paymentMethod->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('payment_methods', ['id' => $paymentMethod->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/payment-methods/{$paymentMethod->id}");

        $response->assertForbidden();
    }

    public function test_user_from_company_b_cannot_view_payment_method_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['payment_methods.view']);

        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $companyA->id]);

        $this->assertTenantIsolation('/api/payment-methods', $userA, $userB, $paymentMethod->id);
    }

    public function test_user_from_company_b_cannot_update_payment_method_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['payment_methods.update']);

        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/payment-methods/{$paymentMethod->id}", ['name' => 'Hacked']);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_user_from_company_b_cannot_delete_payment_method_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['payment_methods.delete']);

        $paymentMethod = PaymentMethod::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/payment-methods/{$paymentMethod->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_index_does_not_leak_payment_methods_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['payment_methods.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['payment_methods.view_any']);

        PaymentMethod::factory()->count(2)->create(['company_id' => $companyA->id]);
        PaymentMethod::factory()->count(3)->create(['company_id' => $companyB->id]);

        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/payment-methods');

        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson('/api/payment-methods');

        $responseA->assertOk();
        $responseB->assertOk();
        $this->assertCount(2, $responseA->json('data'));
        $this->assertCount(3, $responseB->json('data'));
    }

    public function test_search_by_name_returns_matching_payment_methods(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_methods.view_any']);

        PaymentMethod::factory()->create(['company_id' => $company->id, 'name' => 'Cash']);
        PaymentMethod::factory()->create(['company_id' => $company->id, 'name' => 'Check']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payment-methods?search=Cash');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Cash', $response->json('data.0.name'));
    }

    public function test_filter_by_is_active(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['payment_methods.view_any']);

        PaymentMethod::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        PaymentMethod::factory()->create(['company_id' => $company->id, 'is_active' => false]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/payment-methods?is_active=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_active'));
    }
}
