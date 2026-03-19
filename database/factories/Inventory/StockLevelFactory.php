<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Company\Company;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockLevelFactory extends Factory
{
    protected $model = StockLevel::class;

    public function definition(): array
    {
        return [
            'company_id'         => Company::factory(),
            'product_id'         => Product::factory(),
            'warehouse_id'       => Warehouse::factory(),
            'quantity_on_hand'   => $this->faker->numberBetween(0, 1000),
            'quantity_reserved'  => $this->faker->numberBetween(0, 100),
            'quantity_available' => $this->faker->numberBetween(0, 900),
        ];
    }

    public function lowStock(): static
    {
        return $this->state([
            'quantity_on_hand'   => 5,
            'quantity_reserved'  => 0,
            'quantity_available' => 5,
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state([
            'quantity_on_hand'   => 0,
            'quantity_reserved'  => 0,
            'quantity_available' => 0,
        ]);
    }
}
