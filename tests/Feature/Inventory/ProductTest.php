<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Inventory\Product;
use Tests\TestCase;

class ProductTest extends TestCase
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

    private function allProductPermissions(): array
    {
        return [
            'products.view_any', 'products.view', 'products.create',
            'products.update', 'products.delete',
        ];
    }

    private function productPayload(string $name = 'Test Product'): array
    {
        return [
            'code' => 'PROD-TEST-00001',
            'name' => $name,
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_products(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['products.view_any']);

        Product::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/products')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/products')
            ->assertForbidden();
    }

    public function test_index_only_returns_products_from_current_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['products.view_any']);

        Product::factory()->create(['company_id' => $company->id, 'name' => 'MyProduct']);

        $otherCompany = Company::factory()->create();
        Product::factory()->create(['company_id' => $otherCompany->id, 'name' => 'OtherProduct']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/products');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('MyProduct', $data[0]['name']);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_product(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['products.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/products', $this->productPayload())
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Test Product']);
    }

    public function test_store_auto_generates_code_in_prod_format(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['products.create']);

        $payload = ['name' => 'Auto Code Product', 'type' => 'product'];

        $response = $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/products', $payload)
            ->assertCreated();

        $this->assertMatchesRegularExpression('/^PROD-\d{4}-\d{5}$/', $response->json('data.code'));
    }

    public function test_store_requires_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['products.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/products', ['code' => 'PROD-001'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/products', $this->productPayload())
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_product(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['products.view']);

        $product = Product::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $product->id]);
    }

    public function test_show_returns_404_for_nonexistent_product(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['products.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/products/99999')
            ->assertNotFound();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_product(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['products.update']);

        $product = Product::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/products/{$product->id}", ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_delete_product(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['products.delete']);

        $product = Product::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/products/{$product->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    // ─── Low Stock ────────────────────────────────────────────────────────────

    public function test_low_stock_returns_products_below_min_level(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['products.view_any']);

        $product = Product::factory()->create([
            'company_id'      => $company->id,
            'is_stockable'    => true,
            'min_stock_level' => 10,
            'reorder_point'   => 5,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/products/low-stock')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($product->id, $ids);
    }

    // ─── Stock Levels ─────────────────────────────────────────────────────────

    public function test_stock_levels_endpoint_returns_data(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['products.view']);

        $product = Product::factory()->create(['company_id' => $company->id, 'is_stockable' => true]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/products/{$product->id}/stock-levels")
            ->assertOk()
            ->assertJsonStructure(['product_id', 'stock_levels']);
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_user_from_company_b_cannot_view_product_from_company_a(): void
    {
        ['company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userB, $companyB, $this->allProductPermissions());

        $product = Product::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/products/{$product->id}")
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_update_product_from_company_a(): void
    {
        ['company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userB, $companyB, $this->allProductPermissions());

        $product = Product::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->putJson("/api/products/{$product->id}", ['name' => 'Hacked'])
            ->assertNotFound();
    }

    public function test_user_from_company_b_cannot_delete_product_from_company_a(): void
    {
        ['company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userB, $companyB, $this->allProductPermissions());

        $product = Product::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->deleteJson("/api/products/{$product->id}")
            ->assertNotFound();
    }
}
