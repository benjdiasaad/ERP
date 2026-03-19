<?php

namespace Database\Factories;

use App\Models\Caution\Caution;
use App\Models\Caution\CautionType;
use App\Models\Company\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CautionFactory extends Factory
{
    protected $model = Caution::class;

    public function definition(): array
    {
        $issueDate = $this->faker->dateTimeBetween('-6 months');
        $expiryDate = $this->faker->dateTimeBetween($issueDate, '+1 year');

        return [
            'company_id'        => Company::factory(),
            'caution_type_id'   => CautionType::factory(),
            'direction'         => $this->faker->randomElement(['given', 'received']),
            'partner_type'      => $this->faker->randomElement(['customer', 'supplier', 'other']),
            'partner_id'        => $this->faker->numberBetween(1, 100),
            'related_type'      => $this->faker->optional()->randomElement(['App\Models\Sales\SalesOrder', 'App\Models\Purchasing\PurchaseOrder']),
            'related_id'        => $this->faker->optional()->numberBetween(1, 100),
            'amount'            => $this->faker->randomFloat(2, 1000, 100000),
            'currency'          => 'MAD',
            'issue_date'        => $issueDate,
            'expiry_date'       => $expiryDate,
            'return_date'       => null,
            'amount_returned'   => 0.00,
            'amount_forfeited'  => 0.00,
            'bank_name'         => $this->faker->optional()->company(),
            'bank_account'      => $this->faker->optional()->bankAccountNumber(),
            'bank_reference'    => $this->faker->optional()->word(),
            'document_reference' => $this->faker->optional()->word(),
            'status'            => $this->faker->randomElement(['draft', 'active', 'partially_returned', 'returned', 'expired', 'forfeited']),
            'notes'             => $this->faker->optional()->sentence(),
        ];
    }

    public function draft(): self
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'draft',
        ]);
    }

    public function active(): self
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    public function returned(): self
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'returned',
            'return_date' => $this->faker->dateTime(),
        ]);
    }

    public function forfeited(): self
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'forfeited',
        ]);
    }
}
