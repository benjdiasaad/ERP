<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Finance\Tax;
use Tests\TestCase;

class TaxTest extends TestCase
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

    private function taxPayload(string $name = 'TVA 20%'): array
    {
        return [
            'name'        => $name,
            'rate'        => 20.00,
            'description' => 'Standard VAT rate',
            'is_active'   => true,
        ];
    }

    private function allTaxPermissions(): array
    {
        return [
            'taxes.view_any',
            'taxes.view',
            'taxes.create',
            'taxes.update',
            'taxes.delete',
            'taxes.restore',
            'taxes.force_delete',
            'taxes.export',
        ];
    }

    public function test_user_with_permission_can_list_taxes(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['taxes.view_any']);

        Tax::factory()->count(3)->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/taxes');

        $response->assertOk();
        $response->assertJsonStructure(['data' => [['id', 'name', 'rate']]]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/taxes');

        $response->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/taxes');

        $response->assertForbidden();
    }

    public function test_index_only_returns_taxes_from_current_company(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['taxes.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['taxes.view_any']);

        Tax::factory()->create(['company_id' => $companyA->id, 'name' => 'TVA 20%']);
        Tax::factory()->create(['company_id' => $companyB->id, 'name' => 'TVA 10%']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/taxes');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('TVA 20%', $response->json('data.0.name'));
    }

    public function test_user_with_permission_can_create_tax(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['taxes.create']);

        $payload = $this->taxPayload('TVA 14%');
        $payload['rate'] = 14.00;

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/taxes', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'TVA 14%');
        $response->assertJsonPath('data.rate', '14.00');

        $this->assertDatabaseHas('taxes', [
            'company_id' => $company->id,
            'name'       => 'TVA 14%',
            'rate'       => 14.00,
        ]);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/taxes', $this->taxPayload());

        $response->assertForbidden();
    }

    public function test_store_requires_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['taxes.create']);

        $payload = $this->taxPayload();
        unset($payload['name']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/taxes', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    public function test_store_requires_rate(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['taxes.create']);

        $payload = $this->taxPayload();
        unset($payload['rate']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/taxes', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('rate');
    }

    public function test_store_rate_must_be_numeric(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['taxes.create']);

        $payload = $this->taxPayload();
        $payload['rate'] = 'invalid';

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/taxes', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('rate');
    }

    public function test_store_rate_must_be_non_negative(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['taxes.create']);

        $payload = $this->taxPayload();
        $payload['rate'] = -5;

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/taxes', $payload);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('rate');
    }

    public function test_user_with_permission_can_view_tax(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['taxes.view']);

        $tax = Tax::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/taxes/{$tax->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $tax->id);
        $response->assertJsonPath('data.name', $tax->name);
    }

    public function test_show_returns_404_for_nonexistent_tax(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['taxes.view']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/taxes/99999');

        $response->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $tax = Tax::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/taxes/{$tax->id}");

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_update_tax(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['taxes.update']);

        $tax = Tax::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/taxes/{$tax->id}", [
                'name'        => 'TVA Updated',
                'rate'        => 25.00,
                'description' => 'Updated description',
                'is_active'   => false,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'TVA Updated');
        $response->assertJsonPath('data.rate', '25.00');

        $this->assertDatabaseHas('taxes', [
            'id'        => $tax->id,
            'name'      => 'TVA Updated',
            'rate'      => 25.00,
            'is_active' => false,
        ]);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $tax = Tax::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/taxes/{$tax->id}", ['name' => 'Updated']);

        $response->assertForbidden();
    }

    public function test_user_with_permission_can_soft_delete_tax(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['taxes.delete']);

        $tax = Tax::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/taxes/{$tax->id}");

        $response->assertNoContent();

        $this->assertSoftDeleted('taxes', ['id' => $tax->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $tax = Tax::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/taxes/{$tax->id}");

        $response->assertForbidden();
    }

    public function test_user_from_company_b_cannot_view_tax_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['taxes.view']);

        $tax = Tax::factory()->create(['company_id' => $companyA->id]);

        $this->assertTenantIsolation('/api/taxes', $userA, $userB, $tax->id);
    }

    public function test_user_from_company_b_cannot_update_tax_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['taxes.update']);

        $tax = Tax::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/taxes/{$tax->id}", ['name' => 'Hacked']);

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_user_from_company_b_cannot_delete_tax_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userB, $companyB, ['taxes.delete']);

        $tax = Tax::factory()->create(['company_id' => $companyA->id]);

        $response = $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/taxes/{$tax->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_index_does_not_leak_taxes_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['taxes.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['taxes.view_any']);

        Tax::factory()->count(2)->create(['company_id' => $companyA->id]);
        Tax::factory()->count(3)->create(['company_id' => $companyB->id]);

        $responseA = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/taxes');

        $responseB = $this->withHeaders($this->authHeaders($userB))
            ->getJson('/api/taxes');

        $responseA->assertOk();
        $responseB->assertOk();
        $this->assertCount(2, $responseA->json('data'));
        $this->assertCount(3, $responseB->json('data'));
    }

    public function test_search_by_name_returns_matching_taxes(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['taxes.view_any']);

        Tax::factory()->create(['company_id' => $company->id, 'name' => 'TVA 20%']);
        Tax::factory()->create(['company_id' => $company->id, 'name' => 'TVA 10%']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/taxes?search=20');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('TVA 20%', $response->json('data.0.name'));
    }

    public function test_filter_by_is_active(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['taxes.view_any']);

        Tax::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        Tax::factory()->create(['company_id' => $company->id, 'is_active' => false]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/taxes?is_active=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.is_active'));
    }
}
