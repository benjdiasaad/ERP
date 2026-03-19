<?php

declare(strict_types=1);

namespace Database\Factories\Finance;

use App\Models\Company\Company;
use App\Models\Finance\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        $types = ['cash', 'check', 'bank_transfer', 'credit_card', 'mobile', 'other'];
        $type = $this->faker->randomElement($types);

        $names = [
            'cash'           => 'Cash',
            'check'          => 'Check',
            'bank_transfer'  => 'Bank Transfer',
            'credit_card'    => 'Credit Card',
            'mobile'         => 'Mobile Payment',
            'other'          => 'Other',
        ];

        return [
            'company_id'  => Company::factory(),
            'name'        => $names[$type],
            'type'        => $type,
            'description' => $this->faker->optional()->sentence(),
            'is_active'   => true,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Cash',
            'type' => 'cash',
        ]);
    }

    public function check(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Check',
            'type' => 'check',
        ]);
    }

    public function bankTransfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Bank Transfer',
            'type' => 'bank_transfer',
        ]);
    }

    public function creditCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Credit Card',
            'type' => 'credit_card',
        ]);
    }

    public function mobile(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Mobile Payment',
            'type' => 'mobile',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
