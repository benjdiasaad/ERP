<?php

declare(strict_types=1);

namespace Database\Factories\Purchasing;

use App\Models\Company\Company;
use App\Models\Finance\PaymentTerm;
use App\Models\Purchasing\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['individual', 'company']);
        $year = now()->format('Y');
        $seq  = $this->faker->unique()->numberBetween(1, 99999);

        return [
            'company_id'          => Company::factory(),
            'code'                => sprintf('SUPP-%s-%05d', $year, $seq),
            'name'                => $type === 'company' ? $this->faker->company() : $this->faker->name(),
            'type'                => $type,
            'email'               => $this->faker->optional()->safeEmail(),
            'phone'               => $this->faker->optional()->phoneNumber(),
            'mobile'              => $this->faker->optional()->phoneNumber(),
            'address'             => $this->faker->optional()->streetAddress(),
            'city'                => $this->faker->optional()->city(),
            'state'               => $this->faker->optional()->state(),
            'country'             => 'MA',
            'postal_code'         => $this->faker->optional()->postcode(),
            'tax_id'              => $this->faker->optional()->numerify('##########'),
            'ice'                 => $this->faker->optional()->numerify('###############'),
            'rc'                  => $this->faker->optional()->numerify('#####'),
            'payment_term_id'     => null,
            'credit_limit'        => $this->faker->randomFloat(2, 0, 100000),
            'balance'             => 0.00,
            'bank_name'           => $this->faker->optional()->company(),
            'bank_account_number' => $this->faker->optional()->numerify('################'),
            'bank_iban'           => $this->faker->optional()->iban('MA'),
            'bank_swift'          => $this->faker->optional()->swiftBicNumber(),
            'notes'               => $this->faker->optional()->sentence(),
            'is_active'           => true,
        ];
    }

    public function company(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'company',
            'name' => $this->faker->company(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
