<?php

declare(strict_types=1);

namespace Database\Factories\Company;

use App\Models\Company\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $month = str_pad((string) $this->faker->numberBetween(1, 12), 2, '0', STR_PAD_LEFT);
        $day   = str_pad((string) $this->faker->numberBetween(1, 28), 2, '0', STR_PAD_LEFT);

        return [
            'name' => $this->faker->company(),
            'legal_name'          => $this->faker->company() . ' ' . $this->faker->companySuffix(),
            'tax_id'              => strtoupper($this->faker->bothify('??######??')),
            'registration_number' => $this->faker->numerify('RC-######'),
            'email'               => $this->faker->companyEmail(),
            'phone'               => $this->faker->phoneNumber(),
            'address'             => [
                'street'      => $this->faker->streetAddress(),
                'city'        => $this->faker->city(),
                'state'       => $this->faker->state(),
                'postal_code' => $this->faker->postcode(),
                'country'     => $this->faker->country(),
            ],
            'currency'            => 'MAD',
            'fiscal_year_start'   => "{$day}-{$month}",
            'logo'                => null,
            'settings'            => null,
            'is_active'           => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
