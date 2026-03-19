<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Inventory\StockInventory;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockInventoryFactory extends Factory
{
    protected $model = StockInventory::class;

    public function definition(): array
    {
        $year = now()->format('Y');
        $seq  = $this->faker->unique()->numberBetween(1, 99999);

        return [
            'company_id'   => Company::factory(),
            'warehouse_id' => Warehouse::factory(),
            'reference'    => sprintf('INV-%s-%05d', $year, $seq),
            'inventory_date' => now()->toDateString(),
            'status'       => 'draft',
            'notes'        => $this->faker->optional()->sentence(),
            'created_by'   => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => 'in_progress']);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
