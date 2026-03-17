<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Sales\Customer;
use Tests\TestCase;

class CustomerTest extends TestCase
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

    private function companyCustomerPayload(string $name = 'Acme Corp'): array
    {
        return [
            'type' => 'company',
            'name' => $name,
        ];
    }

    private function individualCustomerPayload(string $firstName = 'John', string $lastName = 'Doe'): array
    {
        return [
            'type'       => 'individual',
            'first_name' => $firstName,
            'last_name'  => $lastName,
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_customers(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view_any']);

        Customer::factory()->company()->create(['company_id' => $company->id, 'name' => 'Visible Corp']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/customers');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Visible Corp']);
    }

    public function test_index_returns_paginated_results(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view_any']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/customers');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/customers')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/customers')
            ->assertForbidden();
    }

    public function test_index_only_returns_customers_from_current_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view_any']);

        Customer::factory()->company()->create(['company_id' => $company->id, 'name' => 'MyCustomer']);

        $otherCompany = Company::factory()->create();
        Customer::factory()->company()->create(['company_id' => $otherCompany->id, 'name' => 'OtherCustomer']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/customers');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('MyCustomer', $names);
        $this->assertNotContains('OtherCustomer', $names);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_company_customer(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.create']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/customers', $this->companyCustomerPayload('New Corp'));

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'New Corp', 'type' => 'company']);

        $this->assertDatabaseHas('customers', [
            'name'       => 'New Corp',
            'company_id' => $company->id,
        ]);
    }

    public function test_user_with_permission_can_create_individual_customer(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.create']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/customers', $this->individualCustomerPayload('Jane', 'Smith'));

        $response->assertCreated()
            ->assertJsonFragment(['first_name' => 'Jane', 'last_name' => 'Smith', 'type' => 'individual']);
    }

    public function test_store_auto_generates_code_when_not_provided(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.create']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/customers', $this->companyCustomerPayload());

        $response->assertCreated();
        $code = $response->json('data.code');
        $this->assertNotNull($code);
        $this->assertMatchesRegularExpression('/^CUST-\d{4}-\d{5}$/', $code);
    }

    public function test_store_accepts_custom_code(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.create']);

        $payload = array_merge($this->companyCustomerPayload(), ['code' => 'CUSTOM-001']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/customers', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['code' => 'CUSTOM-001']);
    }

    public function test_store_requires_type(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/customers', ['name' => 'No Type Corp'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_requires_name_for_company_type(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/customers', ['type' => 'company'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_first_and_last_name_for_individual_type(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/customers', ['type' => 'individual'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name']);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/customers', $this->companyCustomerPayload())
            ->assertForbidden();
    }

    public function test_store_validates_email_format(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.create']);

        $payload = array_merge($this->companyCustomerPayload(), ['email' => 'not-an-email']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/customers', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_validates_credit_limit_is_non_negative(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.create']);

        $payload = array_merge($this->companyCustomerPayload(), ['credit_limit' => -100]);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/customers', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['credit_limit']);
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_customer(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/customers/{$customer->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $customer->id]);
    }

    public function test_show_returns_404_for_nonexistent_customer(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/customers/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/customers/{$customer->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_customer(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.update']);

        $customer = Customer::factory()->company()->create([
            'company_id' => $company->id,
            'name'       => 'Old Name',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/customers/{$customer->id}", ['name' => 'Updated Name']);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Updated Name']);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/customers/{$customer->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_update_validates_email_format(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/customers/{$customer->id}", ['email' => 'bad-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_customer(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.delete']);

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/customers/{$customer->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $customer = Customer::factory()->company()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/customers/{$customer->id}")
            ->assertForbidden();
    }

    // ─── Search ───────────────────────────────────────────────────────────────

    public function test_search_by_name_returns_matching_customers(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view_any']);

        Customer::factory()->company()->create(['company_id' => $company->id, 'name' => 'Alpha Corp']);
        Customer::factory()->company()->create(['company_id' => $company->id, 'name' => 'Beta Ltd']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/customers/search?search=Alpha');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Alpha Corp', $names);
        $this->assertNotContains('Beta Ltd', $names);
    }

    public function test_search_by_email_returns_matching_customers(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view_any']);

        Customer::factory()->company()->create([
            'company_id' => $company->id,
            'name'       => 'Email Corp',
            'email'      => 'contact@emailcorp.com',
        ]);
        Customer::factory()->company()->create([
            'company_id' => $company->id,
            'name'       => 'Other Corp',
            'email'      => 'info@other.com',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/customers/search?search=emailcorp');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Email Corp', $names);
        $this->assertNotContains('Other Corp', $names);
    }

    public function test_search_by_code_returns_matching_customers(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view_any']);

        Customer::factory()->company()->create([
            'company_id' => $company->id,
            'name'       => 'Coded Corp',
            'code'       => 'CUST-2024-00001',
        ]);
        Customer::factory()->company()->create([
            'company_id' => $company->id,
            'name'       => 'Other Corp',
            'code'       => 'CUST-2024-00002',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/customers/search?search=00001');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Coded Corp', $names);
        $this->assertNotContains('Other Corp', $names);
    }

    public function test_search_filter_by_type(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view_any']);

        Customer::factory()->company()->create(['company_id' => $company->id, 'name' => 'Corp One']);
        Customer::factory()->individual()->create([
            'company_id' => $company->id,
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/customers?type=company');

        $response->assertOk();
        $types = collect($response->json('data'))->pluck('type')->unique()->toArray();
        $this->assertEquals(['company'], $types);
    }

    public function test_search_filter_by_city(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view_any']);

        Customer::factory()->company()->create([
            'company_id' => $company->id,
            'name'       => 'Casablanca Corp',
            'city'       => 'Casablanca',
        ]);
        Customer::factory()->company()->create([
            'company_id' => $company->id,
            'name'       => 'Rabat Corp',
            'city'       => 'Rabat',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/customers?city=Casablanca');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Casablanca Corp', $names);
        $this->assertNotContains('Rabat Corp', $names);
    }

    public function test_search_filter_by_is_active(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view_any']);

        Customer::factory()->company()->create(['company_id' => $company->id, 'name' => 'Active Corp', 'is_active' => true]);
        Customer::factory()->company()->inactive()->create(['company_id' => $company->id, 'name' => 'Inactive Corp']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/customers?is_active=1');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Active Corp', $names);
        $this->assertNotContains('Inactive Corp', $names);
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view_any']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/customers/search?search=zzznomatch');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    // ─── Credit Info ──────────────────────────────────────────────────────────

    public function test_can_get_credit_info_for_customer(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view']);

        $customer = Customer::factory()->company()->withCreditLimit(10000)->withBalance(3000)->create([
            'company_id' => $company->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/customers/{$customer->id}/credit-info");

        $response->assertOk()
            ->assertJsonFragment([
                'has_credit_limit' => true,
                'is_over_limit'    => false,
            ]);

        $this->assertEquals(7000.0, $response->json('available_credit'));
    }

    public function test_credit_info_shows_over_limit_when_balance_exceeds_limit(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view']);

        $customer = Customer::factory()->company()->withCreditLimit(5000)->withBalance(6000)->create([
            'company_id' => $company->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/customers/{$customer->id}/credit-info");

        $response->assertOk()
            ->assertJsonFragment(['is_over_limit' => true]);
    }

    public function test_credit_info_shows_unlimited_when_credit_limit_is_zero(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['customers.view']);

        $customer = Customer::factory()->company()->withCreditLimit(0)->create([
            'company_id' => $company->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/customers/{$customer->id}/credit-info");

        $response->assertOk()
            ->assertJsonFragment(['has_credit_limit' => false]);
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_customer_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['customers.view']);
        $this->giveUserPermissions($userB, $companyB, ['customers.view']);

        $customer = Customer::factory()->company()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/customers/{$customer->id}")
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_update_customer_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['customers.update']);
        $this->giveUserPermissions($userB, $companyB, ['customers.update']);

        $customer = Customer::factory()->company()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/customers/{$customer->id}", ['name' => 'Hijacked'])
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_delete_customer_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['customers.delete']);
        $this->giveUserPermissions($userB, $companyB, ['customers.delete']);

        $customer = Customer::factory()->company()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/customers/{$customer->id}")
            ->assertNotFound();
    }

    public function test_index_does_not_leak_customers_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['customers.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['customers.view_any']);

        Customer::factory()->company()->create(['company_id' => $companyA->id, 'name' => 'CustomerA']);
        Customer::factory()->company()->create(['company_id' => $companyB->id, 'name' => 'CustomerB']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/customers');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('CustomerA', $names);
        $this->assertNotContains('CustomerB', $names);
    }

    public function test_search_does_not_leak_customers_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['customers.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['customers.view_any']);

        Customer::factory()->company()->create(['company_id' => $companyA->id, 'name' => 'SharedName Corp A']);
        Customer::factory()->company()->create(['company_id' => $companyB->id, 'name' => 'SharedName Corp B']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/customers/search?search=SharedName');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('SharedName Corp A', $names);
        $this->assertNotContains('SharedName Corp B', $names);
    }

    public function test_user_from_company_b_cannot_access_credit_info_of_company_a_customer(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['customers.view']);
        $this->giveUserPermissions($userB, $companyB, ['customers.view']);

        $customer = Customer::factory()->company()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/customers/{$customer->id}/credit-info")
            ->assertNotFound();
    }
}
