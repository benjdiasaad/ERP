<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Company\Company;
use App\Models\Inventory\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        return [
            'company_id'  => Company::factory(),
            'name'        => $this->faker->words(2, true),
            'code'        => strtoupper($this->faker->unique()->lexify('CAT-???')),
            'parent_id'   => null,
            'description' => $this->faker->optional()->sentence(),
            'is_active'   => true,
            'sort_order'  => $this->faker->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withParent(int $parentId): static
    {
        return $this->state(['parent_id' => $parentId]);
    }
}
