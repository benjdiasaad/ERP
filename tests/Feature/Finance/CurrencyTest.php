<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Finance\Currency;
use Tests\TestCase;

class CurrencyTest extends TestCase
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

    private function currencyPayload(string $code = 'USD'): array
    {
        return [
            'code'           => $code,
            'name'           => 'United States Dollar',
            'symbol'         => '$',
            'exchange_rate'  => 1.0,
            'is_default'     => false,
            'is_active'      => true,
        ];
    }

    private function allCurrencyPermissions(): array
    {
        return [
            'currencies.view_any',
            'currencies.view',
            'currencies.create',
            'currencies.update',
            'currencies.delete',
            'currencies.restore',
            'currencies.force_delete',
            'currencies.export',
        ];
    }

    public function test_user_with_permission_can_list_currencies(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.view_any']);

        Currency::factory()->count(3)->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/currencies');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'code', 'name', 'symbol']]]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/currencies');

        $response->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/currencies');

        $response->assertForbidden();
    }

    public function test_index_only_returns_currencies_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['currencies.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['currencies.view_any']);

        Currency::factory()->create(['company_id' => $companyA->id, 'code' => 'USD']);
        Currency::factory()->create(['company_id' => $companyB->id, 'code' => 'EUR']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/currencies');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('USD', $response->json('data.0.code'));
    }

    public function test_user_with_permission_can_create_currency(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.create']);

        $payload = $this->currencyPayload('EUR');

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/currencies', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.code', 'EUR');
        $response->assertJsonPath('data.name', 'United States Dollar');

        $this->assertDatabaseHas('currencies', [
            'company_id' => $company->id,
            'code'       => 'EUR',
        ]);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/currencies', $this->currencyPayload());

        $response->assertForbidden();
    }

    public function test_store_requires_code(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.create']);

        $payload = $this->currencyPayload();
        unset($payload['code']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/currencies', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('code');
    }

    public function test_store_requires_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.create']);

        $payload = $this->currencyPayload();
        unset($payload['name']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/currencies', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_requires_symbol(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.create']);

        $payload = $this->currencyPayload();
        unset($payload['symbol']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/currencies', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('symbol');
    }

    public function test_store_requires_exchange_rate(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.create']);

        $payload = $this->currencyPayload();
        unset($payload['exchange_rate']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/currencies', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('exchange_rate');
    }

    public function test_store_code_must_be_3_characters(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.create']);

        $payload = $this->currencyPayload('US');

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/currencies', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('code');
    }

    public function test_user_with_permission_can_view_currency(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.view']);

        $currency = Currency::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/currencies/{$currency->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $currency->id);
        $response->assertJsonPath('data.code', $currency->code);
    }

    public function test_show_returns_404_for_nonexistent_currency(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.view']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/currencies/99999');

        $response->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $currency = Currency::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/currencies/{$currency->id}");

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_update_currency(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.update']);

        $currency = Currency::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/currencies/{$currency->id}", [
                'name'           => 'Updated Name',
                'exchange_rate'  => 1.5,
                'is_active'      => false,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Name');
        $this->assertEquals('1.500000', $response->json('data.exchange_rate'));

        $this->assertDatabaseHas('currencies', [
            'id'             => $currency->id,
            'name'           => 'Updated Name',
            'exchange_rate'  => 1.5,
            'is_active'      => false,
        ]);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $currency = Currency::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/currencies/{$currency->id}", ['name' => 'Updated']);

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_soft_delete_currency(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.delete']);

        $currency = Currency::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/currencies/{$currency->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('currencies', ['id' => $currency->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $currency = Currency::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/currencies/{$currency->id}");

        $response->assertForbidden();
    }

    public function test_user_from_company_b_cannot_view_currency_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['currencies.view']);

        $currency = Currency::factory()->create(['company_id' => $companyA->id]);

        $this->assertTenantIsolation('/api/currencies', $userA, $userB, $currency->id);
    }

    public function test_user_from_company_b_cannot_update_currency_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['currencies.update']);

        $currency = Currency::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/currencies/{$currency->id}", ['name' => 'Hacked']);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_user_from_company_b_cannot_delete_currency_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['currencies.delete']);

        $currency = Currency::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/currencies/{$currency->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_index_does_not_leak_currencies_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['currencies.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['currencies.view_any']);

        Currency::factory()->count(2)->create(['company_id' => $companyA->id]);
        Currency::factory()->count(3)->create(['company_id' => $companyB->id]);

        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/currencies');

        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson('/api/currencies');

        $responseA->assertOk();
        $responseB->assertOk();
        $this->assertCount(2, $responseA->json('data'));
        $this->assertCount(3, $responseB->json('data'));
    }

    public function test_search_by_code_returns_matching_currencies(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.view_any']);

        Currency::factory()->create(['company_id' => $company->id, 'code' => 'USD']);
        Currency::factory()->create(['company_id' => $company->id, 'code' => 'EUR']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/currencies?search=USD');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('USD', $response->json('data.0.code'));
    }

    public function test_search_by_name_returns_matching_currencies(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.view_any']);

        Currency::factory()->create(['company_id' => $company->id, 'name' => 'US Dollar']);
        Currency::factory()->create(['company_id' => $company->id, 'name' => 'Euro']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/currencies?search=Dollar');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('US Dollar', $response->json('data.0.name'));
    }

    public function test_filter_by_is_active(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['currencies.view_any']);

        Currency::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        Currency::factory()->create(['company_id' => $company->id, 'is_active' => false]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/currencies?is_active=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_active'));
    }
}
