<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Company\Company;
use App\Models\Finance\Currency;
use App\Models\Finance\PaymentTerm;
use App\Models\Sales\Customer;
use App\Models\Sales\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        $year = now()->format('Y');
        $seq  = $this->faker->unique()->numberBetween(1, 99999);
        $quoteDate = $this->faker->dateTimeBetween('-6 months', 'now');

        return [
            'company_id'          => Company::factory(),
            'customer_id'         => Customer::factory(),
            'reference'           => sprintf('DEV-%s-%05d', $year, $seq),
            'quote_date'          => $quoteDate,
            'validity_date'       => $this->faker->dateTimeBetween($quoteDate, '+30 days'),
            'status'              => 'draft',
            'subtotal_ht'         => 0.00,
            'total_discount'      => 0.00,
            'total_tax'           => 0.00,
            'total_ttc'           => 0.00,
            'currency_id'         => null,
            'payment_term_id'     => null,
            'notes'               => $this->faker->optional()->sentence(),
            'terms_and_conditions' => null,
            'converted_to_order_id' => null,
            'sent_at'             => null,
            'accepted_at'         => null,
            'rejected_at'         => null,
            'rejection_reason'    => null,
            'created_by'          => null,
            'updated_by'          => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'  => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => 'accepted',
            'sent_at'     => now()->subDays(3),
            'accepted_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => 'rejected',
            'sent_at'          => now()->subDays(3),
            'rejected_at'      => now(),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'expired',
            'sent_at'      => now()->subDays(40),
            'validity_date' => now()->subDays(10),
        ]);
    }

    public function withTotals(float $subtotalHt, float $totalTax = 0, float $totalDiscount = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'subtotal_ht'    => $subtotalHt,
            'total_discount' => $totalDiscount,
            'total_tax'      => $totalTax,
            'total_ttc'      => $subtotalHt - $totalDiscount + $totalTax,
        ]);
    }
}
