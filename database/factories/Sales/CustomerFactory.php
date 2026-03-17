<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Company\Company;
use App\Models\Finance\Currency;
use App\Models\Finance\PaymentTerm;
use App\Models\Sales\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['individual', 'company']);
        $year = now()->format('Y');
        $seq  = $this->faker->unique()->numberBetween(1, 99999);

        return [
            'company_id'       => Company::factory(),
            'code'             => sprintf('CUST-%s-%05d', $year, $seq),
            'type'             => $type,
            'name'             => $type === 'company' ? $this->faker->company() : null,
            'first_name'       => $type === 'individual' ? $this->faker->firstName() : null,
            'last_name'        => $type === 'individual' ? $this->faker->lastName() : null,
            'email'            => $this->faker->optional()->safeEmail(),
            'phone'            => $this->faker->optional()->phoneNumber(),
            'mobile'           => $this->faker->optional()->phoneNumber(),
            'address'          => $this->faker->optional()->streetAddress(),
            'city'             => $this->faker->optional()->city(),
            'state'            => $this->faker->optional()->state(),
            'country'          => 'MA',
            'postal_code'      => $this->faker->optional()->postcode(),
            'tax_id'           => $this->faker->optional()->numerify('##########'),
            'ice'              => $this->faker->optional()->numerify('###############'),
            'rc'               => $this->faker->optional()->numerify('#####'),
            'payment_terms_id' => null,
            'credit_limit'     => $this->faker->randomFloat(2, 0, 100000),
            'balance'          => 0.00,
            'currency_id'      => null,
            'notes'            => $this->faker->optional()->sentence(),
            'is_active'        => true,
        ];
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'       => 'individual',
            'name'       => null,
            'first_name' => $this->faker->firstName(),
            'last_name'  => $this->faker->lastName(),
        ]);
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'       => 'company',
            'name'       => $this->faker->company(),
            'first_name' => null,
            'last_name'  => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function withBalance(float $balance): static
    {
        return $this->state(fn (array $attributes) => ['balance' => $balance]);
    }

    public function withCreditLimit(float $limit): static
    {
        return $this->state(fn (array $attributes) => ['credit_limit' => $limit]);
    }
}
