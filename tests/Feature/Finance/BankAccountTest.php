<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Currency;
use Tests\TestCase;

class BankAccountTest extends TestCase
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

    private function bankAccountPayload(?int $currencyId = null): array
    {
        return [
            'name'           => 'Main Bank Account',
            'bank'           => 'Bank of Test',
            'account_number' => '1234567890',
            'iban'           => 'FR1420041010050500013M02606',
            'swift'          => 'BNPAFRPP',
            'currency_id'    => $currencyId ?? Currency::factory()->create()->id,
            'balance'        => 5000.00,
            'is_active'      => true,
        ];
    }

    private function allBankAccountPermissions(): array
    {
        return [
            'bank_accounts.view_any',
            'bank_accounts.view',
            'bank_accounts.create',
            'bank_accounts.update',
            'bank_accounts.delete',
            'bank_accounts.restore',
            'bank_accounts.force_delete',
            'bank_accounts.export',
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_bank_accounts(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.view_any']);

        BankAccount::factory()->count(3)->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/bank-accounts');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'name', 'bank', 'account_number']]]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/bank-accounts');

        $response->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/bank-accounts');

        $response->assertForbidden();
    }

    public function test_index_only_returns_accounts_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['bank_accounts.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['bank_accounts.view_any']);

        BankAccount::factory()->create(['company_id' => $companyA->id, 'name' => 'Bank A']);
        BankAccount::factory()->create(['company_id' => $companyB->id, 'name' => 'Bank B']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/bank-accounts');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Bank A', $response->json('data.0.name'));
    }

    public function test_index_can_filter_by_currency(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.view_any']);

        $currencyUSD = Currency::factory()->create(['code' => 'USD']);
        $currencyEUR = Currency::factory()->create(['code' => 'EUR']);

        BankAccount::factory()->create(['company_id' => $company->id, 'currency_id' => $currencyUSD->id]);
        BankAccount::factory()->create(['company_id' => $company->id, 'currency_id' => $currencyEUR->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/bank-accounts?currency_id={$currencyUSD->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($currencyUSD->id, $response->json('data.0.currency_id'));
    }

    public function test_index_can_filter_by_is_active(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.view_any']);

        BankAccount::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        BankAccount::factory()->create(['company_id' => $company->id, 'is_active' => false]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/bank-accounts?is_active=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_active'));
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_bank_account(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.create']);

        $currency = Currency::factory()->create();
        $payload = $this->bankAccountPayload($currency->id);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/bank-accounts', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Main Bank Account');
        $response->assertJsonPath('data.currency_id', $currency->id);

        $this->assertDatabaseHas('bank_accounts', [
            'company_id' => $company->id,
            'name'       => 'Main Bank Account',
        ]);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $currency = Currency::factory()->create();
        $payload = $this->bankAccountPayload($currency->id);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/bank-accounts', $payload);

        $response->assertForbidden();
    }

    public function test_store_requires_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.create']);

        $currency = Currency::factory()->create();
        $payload = $this->bankAccountPayload($currency->id);
        unset($payload['name']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/bank-accounts', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_requires_bank(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.create']);

        $currency = Currency::factory()->create();
        $payload = $this->bankAccountPayload($currency->id);
        unset($payload['bank']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/bank-accounts', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('bank');
    }

    public function test_store_requires_account_number(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.create']);

        $currency = Currency::factory()->create();
        $payload = $this->bankAccountPayload($currency->id);
        unset($payload['account_number']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/bank-accounts', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('account_number');
    }

    public function test_store_requires_currency_id(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.create']);

        $payload = $this->bankAccountPayload();
        unset($payload['currency_id']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/bank-accounts', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('currency_id');
    }

    public function test_store_initializes_balance_to_zero_if_not_provided(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.create']);

        $currency = Currency::factory()->create();
        $payload = $this->bankAccountPayload($currency->id);
        unset($payload['balance']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/bank-accounts', $payload);

        $response->assertCreated();
        $this->assertEquals('0.00', $response->json('data.balance'));
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_bank_account(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.view']);

        $account = BankAccount::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/bank-accounts/{$account->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $account->id);
        $response->assertJsonPath('data.name', $account->name);
    }

    public function test_show_returns_404_for_nonexistent_account(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.view']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/bank-accounts/99999');

        $response->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $account = BankAccount::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/bank-accounts/{$account->id}");

        $response->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_bank_account(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.update']);

        $account = BankAccount::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/bank-accounts/{$account->id}", [
                'name'      => 'Updated Account Name',
                'is_active' => false,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Updated Account Name');
        $this->assertFalse($response->json('data.is_active'));

        $this->assertDatabaseHas('bank_accounts', [
            'id'   => $account->id,
            'name' => 'Updated Account Name',
        ]);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $account = BankAccount::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/bank-accounts/{$account->id}", ['name' => 'Hacked']);

        $response->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_bank_account(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.delete']);

        $account = BankAccount::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/bank-accounts/{$account->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('bank_accounts', ['id' => $account->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $account = BankAccount::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/bank-accounts/{$account->id}");

        $response->assertForbidden();
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_account_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['bank_accounts.view']);

        $account = BankAccount::factory()->create(['company_id' => $companyA->id]);

        $this->assertTenantIsolation('/api/bank-accounts', $userA, $userB, $account->id);
    }

    public function test_user_from_company_b_cannot_update_account_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['bank_accounts.update']);

        $account = BankAccount::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/bank-accounts/{$account->id}", ['name' => 'Hacked']);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_user_from_company_b_cannot_delete_account_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['bank_accounts.delete']);

        $account = BankAccount::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/bank-accounts/{$account->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_index_does_not_leak_accounts_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['bank_accounts.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['bank_accounts.view_any']);

        BankAccount::factory()->count(2)->create(['company_id' => $companyA->id]);
        BankAccount::factory()->count(3)->create(['company_id' => $companyB->id]);

        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/bank-accounts');

        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson('/api/bank-accounts');

        $responseA->assertOk();
        $responseB->assertOk();
        $this->assertCount(2, $responseA->json('data'));
        $this->assertCount(3, $responseB->json('data'));
    }

    // ─── Search ───────────────────────────────────────────────────────────────

    public function test_search_by_name_returns_matching_accounts(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.view_any']);

        BankAccount::factory()->create(['company_id' => $company->id, 'name' => 'Main Account']);
        BankAccount::factory()->create(['company_id' => $company->id, 'name' => 'Savings Account']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/bank-accounts?search=Main');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Main Account', $response->json('data.0.name'));
    }

    public function test_search_by_bank_name_returns_matching_accounts(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.view_any']);

        BankAccount::factory()->create(['company_id' => $company->id, 'bank' => 'Bank of America']);
        BankAccount::factory()->create(['company_id' => $company->id, 'bank' => 'Chase Bank']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/bank-accounts?search=Chase');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Chase Bank', $response->json('data.0.bank'));
    }

    public function test_search_by_iban_returns_matching_accounts(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.view_any']);

        BankAccount::factory()->create(['company_id' => $company->id, 'iban' => 'FR1420041010050500013M02606']);
        BankAccount::factory()->create(['company_id' => $company->id, 'iban' => 'DE89370400440532013000']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/bank-accounts?search=FR14');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('FR1420041010050500013M02606', $response->json('data.0.iban'));
    }

    // ─── Balance Operations ───────────────────────────────────────────────────

    public function test_balance_is_stored_with_correct_precision(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.create']);

        $currency = Currency::factory()->create();
        $payload = $this->bankAccountPayload($currency->id);
        $payload['balance'] = 1234.56;

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/bank-accounts', $payload);

        $response->assertCreated();
        $this->assertEquals('1234.56', $response->json('data.balance'));
    }

    public function test_currency_relationship_is_loaded(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.view']);

        $currency = Currency::factory()->create(['code' => 'USD']);
        $account = BankAccount::factory()->create(['company_id' => $company->id, 'currency_id' => $currency->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/bank-accounts/{$account->id}");

        $response->assertOk();
        $response->assertJsonPath('data.currency.code', 'USD');
    }

    public function test_active_scope_filters_inactive_accounts(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.view_any']);

        BankAccount::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        BankAccount::factory()->create(['company_id' => $company->id, 'is_active' => false]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/bank-accounts?is_active=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_active'));
    }

    public function test_iban_and_swift_are_optional(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['bank_accounts.create']);

        $currency = Currency::factory()->create();
        $payload = $this->bankAccountPayload($currency->id);
        unset($payload['iban'], $payload['swift']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/bank-accounts', $payload);

        $response->assertCreated();
        $this->assertNull($response->json('data.iban'));
        $this->assertNull($response->json('data.swift'));
    }
}
