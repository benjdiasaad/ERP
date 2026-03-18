<?php

declare(strict_types=1);

namespace Tests\Feature\Purchasing;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Purchasing\Supplier;
use Tests\TestCase;

class SupplierTest extends TestCase
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

    private function supplierPayload(string $name = 'Acme Supplies'): array
    {
        return [
            'name' => $name,
            'type' => 'company',
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_suppliers(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.view_any']);

        Supplier::factory()->create(['company_id' => $company->id, 'name' => 'Visible Supplier']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suppliers');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Visible Supplier']);
    }

    public function test_index_returns_paginated_results(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.view_any']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suppliers');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['total', 'per_page', 'current_page']]);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/suppliers')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suppliers')
            ->assertForbidden();
    }

    public function test_index_only_returns_suppliers_from_current_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.view_any']);

        Supplier::factory()->create(['company_id' => $company->id, 'name' => 'MySupplier']);

        $otherCompany = Company::factory()->create();
        Supplier::factory()->create(['company_id' => $otherCompany->id, 'name' => 'OtherSupplier']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suppliers');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('MySupplier', $names);
        $this->assertNotContains('OtherSupplier', $names);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_supplier(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.create']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/suppliers', $this->supplierPayload('New Supplier'));

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'New Supplier']);

        $this->assertDatabaseHas('suppliers', [
            'name'       => 'New Supplier',
            'company_id' => $company->id,
        ]);
    }

    public function test_store_auto_generates_code_when_not_provided(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.create']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/suppliers', $this->supplierPayload());

        $response->assertCreated();
        $code = $response->json('data.code');
        $this->assertNotNull($code);
        $this->assertMatchesRegularExpression('/^SUPP-\d{5}$/', $code);
    }

    public function test_store_requires_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/suppliers', ['type' => 'company'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/suppliers', $this->supplierPayload())
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_supplier(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.view']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/suppliers/{$supplier->id}");

        $response->assertOk()
            ->assertJsonFragment(['id' => $supplier->id]);
    }

    public function test_show_returns_404_for_nonexistent_supplier(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suppliers/99999')
            ->assertNotFound();
    }

    public function test_show_requires_view_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/suppliers/{$supplier->id}")
            ->assertForbidden();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_supplier(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.update']);

        $supplier = Supplier::factory()->create([
            'company_id' => $company->id,
            'name'       => 'Old Name',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/suppliers/{$supplier->id}", ['name' => 'Updated Name']);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'name' => 'Updated Name']);
    }

    public function test_update_requires_update_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/suppliers/{$supplier->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_soft_delete_supplier(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.delete']);

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/suppliers/{$supplier->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_destroy_requires_delete_permission(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();

        $supplier = Supplier::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/suppliers/{$supplier->id}")
            ->assertForbidden();
    }

    // ─── Search ───────────────────────────────────────────────────────────────

    public function test_search_by_name_returns_matching_suppliers(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.view_any']);

        Supplier::factory()->create(['company_id' => $company->id, 'name' => 'Alpha Supplies']);
        Supplier::factory()->create(['company_id' => $company->id, 'name' => 'Beta Supplies']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suppliers/search?q=Alpha');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Alpha Supplies', $names);
        $this->assertNotContains('Beta Supplies', $names);
    }

    public function test_search_by_code_returns_matching_suppliers(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.view_any']);

        Supplier::factory()->create([
            'company_id' => $company->id,
            'name'       => 'Coded Supplier',
            'code'       => 'SUPP-00001',
        ]);
        Supplier::factory()->create([
            'company_id' => $company->id,
            'name'       => 'Other Supplier',
            'code'       => 'SUPP-00002',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suppliers/search?q=00001');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Coded Supplier', $names);
        $this->assertNotContains('Other Supplier', $names);
    }

    public function test_search_by_email_returns_matching_suppliers(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.view_any']);

        Supplier::factory()->create([
            'company_id' => $company->id,
            'name'       => 'Email Supplier',
            'email'      => 'contact@emailsupplier.com',
        ]);
        Supplier::factory()->create([
            'company_id' => $company->id,
            'name'       => 'Other Supplier',
            'email'      => 'info@other.com',
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suppliers/search?q=emailsupplier');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Email Supplier', $names);
        $this->assertNotContains('Other Supplier', $names);
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['suppliers.view_any']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/suppliers/search?q=zzznomatch');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_supplier_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['suppliers.view']);
        $this->giveUserPermissions($userB, $companyB, ['suppliers.view']);

        $supplier = Supplier::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/suppliers/{$supplier->id}")
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_update_supplier_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['suppliers.update']);
        $this->giveUserPermissions($userB, $companyB, ['suppliers.update']);

        $supplier = Supplier::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/suppliers/{$supplier->id}", ['name' => 'Hijacked'])
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_delete_supplier_from_company_a(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['suppliers.delete']);
        $this->giveUserPermissions($userB, $companyB, ['suppliers.delete']);

        $supplier = Supplier::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/suppliers/{$supplier->id}")
            ->assertNotFound();
    }

    public function test_index_does_not_leak_suppliers_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['suppliers.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['suppliers.view_any']);

        Supplier::factory()->create(['company_id' => $companyA->id, 'name' => 'SupplierA']);
        Supplier::factory()->create(['company_id' => $companyB->id, 'name' => 'SupplierB']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/suppliers');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('SupplierA', $names);
        $this->assertNotContains('SupplierB', $names);
    }

    public function test_search_does_not_leak_suppliers_across_companies(): void
    {
        ['user' => $userA, 'company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();

        $this->giveUserPermissions($userA, $companyA, ['suppliers.view_any']);
        $this->giveUserPermissions($userB, $companyB, ['suppliers.view_any']);

        Supplier::factory()->create(['company_id' => $companyA->id, 'name' => 'SharedName Supplier A']);
        Supplier::factory()->create(['company_id' => $companyB->id, 'name' => 'SharedName Supplier B']);

        $response = $this->withHeaders($this->authHeaders($userA))
            ->getJson('/api/suppliers/search?q=SharedName');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('SharedName Supplier A', $names);
        $this->assertNotContains('SharedName Supplier B', $names);
    }
}
