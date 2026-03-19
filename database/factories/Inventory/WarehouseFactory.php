<?php

declare(strict_types=1);

namespace Database\Factories\Inventory;

use App\Models\Company\Company;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'company_id'  => Company::factory(),
            'code'        => 'WH-' . $this->faker->unique()->numerify('###'),
            'name'        => $this->faker->word() . ' Warehouse',
            'address'     => $this->faker->address(),
            'city'        => $this->faker->city(),
            'state'       => $this->faker->state(),
            'country'     => $this->faker->country(),
            'postal_code' => $this->faker->postcode(),
            'manager_id'  => null,
            'is_default'  => false,
            'is_active'   => true,
            'notes'       => $this->faker->optional()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
