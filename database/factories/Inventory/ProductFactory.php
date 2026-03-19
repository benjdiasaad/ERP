<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Company\Company;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $year = now()->format('Y');

        return [
            'company_id'      => Company::factory(),
            'category_id'     => null,
            'code'            => 'PROD-' . $year . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'name'            => $this->faker->words(3, true),
            'description'     => $this->faker->optional()->sentence(),
            'type'            => $this->faker->randomElement(['product', 'service', 'consumable']),
            'unit'            => $this->faker->randomElement(['pcs', 'kg', 'l', 'm', null]),
            'purchase_price'  => $this->faker->randomFloat(2, 1, 10000),
            'sale_price'      => $this->faker->randomFloat(2, 1, 15000),
            'tax_rate'        => $this->faker->randomElement([0, 7, 10, 14, 20]),
            'barcode'         => $this->faker->optional()->ean13(),
            'min_stock_level' => 0,
            'max_stock_level' => null,
            'reorder_point'   => 0,
            'is_active'       => true,
            'is_purchasable'  => true,
            'is_sellable'     => true,
            'is_stockable'    => true,
            'notes'           => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function service(): static
    {
        return $this->state(['type' => 'service', 'is_stockable' => false]);
    }

    public function consumable(): static
    {
        return $this->state(['type' => 'consumable']);
    }

    public function lowStock(): static
    {
        return $this->state(['reorder_point' => 10, 'min_stock_level' => 5]);
    }

    public function withLowStockAlert(float $minLevel): static
    {
        return $this->state(['min_stock_level' => $minLevel]);
    }
}
