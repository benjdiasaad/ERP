<?php

declare(strict_types=1);

namespace Database\Factories\Finance;

use App\Models\Company\Company;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    protected $model = BankAccount::class;

    public function definition(): array
    {
        return [
            'company_id'    => Company::factory(),
            'name'          => $this->faker->words(2, true),
            'bank'          => $this->faker->company(),
            'account_number' => $this->faker->numerify('####################'),
            'iban'          => $this->faker->iban(),
            'swift'         => $this->faker->swiftBicNumber(),
            'currency_id'   => Currency::factory(),
            'balance'       => $this->faker->randomFloat(2, 0, 100000),
            'is_active'     => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function withBalance(float $balance): static
    {
        return $this->state(fn (array $attributes) => ['balance' => $balance]);
    }

    public function withCurrency(Currency $currency): static
    {
        return $this->state(fn (array $attributes) => ['currency_id' => $currency->id]);
    }

    public function withCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => ['company_id' => $company->id]);
    }
}
