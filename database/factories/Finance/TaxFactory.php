<?php

declare(strict_types=1);

namespace Database\Factories\Finance;

use App\Models\Company\Company;
use App\Models\Finance\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tax>
 */
class TaxFactory extends Factory
{
    protected $model = Tax::class;

    public function definition(): array
    {
        $rates = [20, 14, 10, 7, 0];
        $rate = $this->faker->randomElement($rates);

        return [
            'company_id'  => Company::factory(),
            'name'        => $rate === 0 ? 'Exempt' : "TVA {$rate}%",
            'rate'        => $rate,
            'description' => $this->faker->optional()->sentence(),
            'is_active'   => true,
        ];
    }

    public function tva20(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'TVA 20%',
            'rate' => 20,
        ]);
    }

    public function tva14(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'TVA 14%',
            'rate' => 14,
        ]);
    }

    public function tva10(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'TVA 10%',
            'rate' => 10,
        ]);
    }

    public function tva7(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'TVA 7%',
            'rate' => 7,
        ]);
    }

    public function exempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Exempt',
            'rate' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
