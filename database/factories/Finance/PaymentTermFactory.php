<?php

declare(strict_types=1);

namespace Database\Factories\Finance;

use App\Models\Company\Company;
use App\Models\Finance\PaymentTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentTerm>
 */
class PaymentTermFactory extends Factory
{
    protected $model = PaymentTerm::class;

    public function definition(): array
    {
        $days = $this->faker->randomElement([0, 7, 14, 30, 45, 60, 90]);

        return [
            'company_id'  => Company::factory(),
            'name'        => $days === 0 ? 'Immediate' : "{$days} days",
            'days'        => $days,
            'description' => $this->faker->optional()->sentence(),
            'is_active'   => true,
        ];
    }

    public function immediate(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Immediate',
            'days' => 0,
        ]);
    }

    public function net7(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Net 7',
            'days' => 7,
        ]);
    }

    public function net14(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Net 14',
            'days' => 14,
        ]);
    }

    public function net30(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Net 30',
            'days' => 30,
        ]);
    }

    public function net45(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Net 45',
            'days' => 45,
        ]);
    }

    public function net60(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Net 60',
            'days' => 60,
        ]);
    }

    public function net90(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Net 90',
            'days' => 90,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
