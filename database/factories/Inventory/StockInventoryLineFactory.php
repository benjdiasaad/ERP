<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockInventory;
use App\Models\Inventory\StockInventoryLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockInventoryLineFactory extends Factory
{
    protected $model = StockInventoryLine::class;

    public function definition(): array
    {
        $theoreticalQty = $this->faker->numberBetween(10, 100);
        $countedQty = $this->faker->numberBetween(8, 102);

        return [
            'stock_inventory_id' => StockInventory::factory(),
            'product_id'         => Product::factory(),
            'theoretical_qty'    => $theoreticalQty,
            'counted_qty'        => $countedQty,
            'variance'           => $countedQty - $theoreticalQty,
            'notes'              => $this->faker->optional()->sentence(),
        ];
    }

    public function withVariance(): static
    {
        return $this->state(fn (array $attributes) => [
            'variance' => $attributes['counted_qty'] - $attributes['theoretical_qty'],
        ]);
    }
}
