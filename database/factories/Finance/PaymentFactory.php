<?php

declare(strict_types=1);

namespace Database\Factories\Finance;

use App\Models\Company\Company;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentMethod;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'company_id'        => Company::factory(),
            'reference'         => $this->faker->unique()->numerify('PAY-####'),
            'payable_type'      => Invoice::class,
            'payable_id'        => Invoice::factory(),
            'direction'         => $this->faker->randomElement(['incoming', 'outgoing']),
            'amount'            => $this->faker->randomFloat(2, 0.01, 50000),
            'payment_method_id' => PaymentMethod::factory(),
            'bank_account_id'   => BankAccount::factory(),
            'payment_date'      => $this->faker->dateTime(),
            'status'            => 'pending',
            'notes'             => $this->faker->optional()->sentence(),
            'confirmed_at'      => null,
            'confirmed_by'      => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_by' => 1,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'pending',
            'confirmed_at' => null,
            'confirmed_by' => null,
        ]);
    }

    public function incoming(): static
    {
        return $this->state(fn (array $attributes) => ['direction' => 'incoming']);
    }

    public function outgoing(): static
    {
        return $this->state(fn (array $attributes) => ['direction' => 'outgoing']);
    }

    public function withCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => ['company_id' => $company->id]);
    }

    public function withPayable(string $type, int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'payable_type' => $type,
            'payable_id'   => $id,
        ]);
    }

    public function withPaymentMethod(PaymentMethod $method): static
    {
        return $this->state(fn (array $attributes) => ['payment_method_id' => $method->id]);
    }

    public function withBankAccount(BankAccount $account): static
    {
        return $this->state(fn (array $attributes) => ['bank_account_id' => $account->id]);
    }
}
