<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use Tests\TestCase;

class CategoryTest extends TestCase
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

    private function allCategoryPermissions(): array
    {
        return [
            'product_categories.view_any', 'product_categories.view', 'product_categories.create',
            'product_categories.update', 'product_categories.delete',
        ];
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_list_categories(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['product_categories.view_any']);

        ProductCategory::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/product-categories')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/product-categories')->assertUnauthorized();
    }

    public function test_index_requires_view_any_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/product-categories')
            ->assertForbidden();
    }

    public function test_index_only_returns_categories_from_current_company(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['product_categories.view_any']);

        ProductCategory::factory()->create(['company_id' => $company->id, 'name' => 'MyCat']);

        $otherCompany = Company::factory()->create();
        ProductCategory::factory()->create(['company_id' => $otherCompany->id, 'name' => 'OtherCat']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/product-categories');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('MyCat', $data[0]['name']);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_create_category(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['product_categories.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/product-categories', ['name' => 'Electronics'])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Electronics']);
    }

    public function test_store_requires_name(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['product_categories.create']);

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/product-categories', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_create_permission(): void
    {
        ['user' => $user] = $this->setUpCompanyAndUser();

        $this->withHeaders($this->authHeaders($user))
            ->postJson('/api/product-categories', ['name' => 'Electronics'])
            ->assertForbidden();
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_view_category(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['product_categories.view']);

        $category = ProductCategory::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson("/api/product-categories/{$category->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $category->id]);
    }

    public function test_show_returns_404_for_nonexistent_category(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['product_categories.view']);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/product-categories/99999')
            ->assertNotFound();
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_update_category(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['product_categories.update']);

        $category = ProductCategory::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->putJson("/api/product-categories/{$category->id}", ['name' => 'Updated Category'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Category']);
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_with_permission_can_delete_category(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['product_categories.delete']);

        $category = ProductCategory::factory()->create(['company_id' => $company->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/product-categories/{$category->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('product_categories', ['id' => $category->id]);
    }

    // ─── Tree ─────────────────────────────────────────────────────────────────

    public function test_can_get_category_tree(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['product_categories.view_any']);

        ProductCategory::factory()->create(['company_id' => $company->id, 'parent_id' => null]);

        $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/product-categories/tree')
            ->assertOk()
            ->assertJsonStructure([]);
    }

    public function test_category_tree_includes_children(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['product_categories.view_any']);

        $parent = ProductCategory::factory()->create([
            'company_id' => $company->id,
            'name'       => 'Parent Category',
            'parent_id'  => null,
        ]);

        ProductCategory::factory()->create([
            'company_id' => $company->id,
            'name'       => 'Child Category',
            'parent_id'  => $parent->id,
        ]);

        $response = $this->withHeaders($this->authHeaders($user))
            ->getJson('/api/product-categories/tree')
            ->assertOk();

        $tree = $response->json();
        $this->assertIsArray($tree);
        $this->assertNotEmpty($tree);

        $parentNode = collect($tree)->firstWhere('id', $parent->id);
        $this->assertNotNull($parentNode);
        $this->assertNotEmpty($parentNode['children']);
        $this->assertEquals('Child Category', $parentNode['children'][0]['name']);
    }

    // ─── Tenant Isolation ─────────────────────────────────────────────────────

    public function test_delete_category_with_products_fails(): void
    {
        ['user' => $user, 'company' => $company] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($user, $company, ['product_categories.delete']);

        $category = ProductCategory::factory()->create(['company_id' => $company->id]);
        Product::factory()->create(['company_id' => $company->id, 'category_id' => $category->id]);

        $this->withHeaders($this->authHeaders($user))
            ->deleteJson("/api/product-categories/{$category->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('product_categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_user_from_company_b_cannot_view_category_from_company_a(): void
    {
        ['company' => $companyA] = $this->setUpCompanyAndUser();
        ['user' => $userB, 'company' => $companyB] = $this->setUpCompanyAndUser();
        $this->giveUserPermissions($userB, $companyB, $this->allCategoryPermissions());

        $category = ProductCategory::factory()->create(['company_id' => $companyA->id]);

        $this->withHeaders($this->authHeaders($userB))
            ->getJson("/api/product-categories/{$category->id}")
            ->assertNotFound();
    }
}
