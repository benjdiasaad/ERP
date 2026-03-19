<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Company\Company;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'company_id'   => Company::factory(),
            'product_id'   => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'type'         => $this->faker->randomElement(['in', 'out', 'transfer', 'adjustment', 'return', 'initial']),
            'quantity'     => $this->faker->numberBetween(1, 100),
            'reference'    => $this->faker->optional()->word(),
            'source_type'  => null,
            'source_id'    => null,
            'notes'        => $this->faker->optional()->sentence(),
            'movement_date' => now(),
        ];
    }

    public function in(): static
    {
        return $this->state(['type' => 'in']);
    }

    public function out(): static
    {
        return $this->state(['type' => 'out']);
    }

    public function transfer(): static
    {
        return $this->state(['type' => 'transfer']);
    }

    public function adjustment(): static
    {
        return $this->state(['type' => 'adjustment']);
    }
}
