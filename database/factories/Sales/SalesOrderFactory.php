<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Company\Company;
use App\Models\Finance\Currency;
use App\Models\Finance\PaymentTerm;
use App\Models\Sales\Customer;
use App\Models\Sales\SalesOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrder>
 */
class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        $year      = now()->format('Y');
        $seq       = $this->faker->unique()->numberBetween(1, 99999);
        $orderDate = $this->faker->dateTimeBetween('-6 months', 'now');

        return [
            'company_id'             => Company::factory(),
            'customer_id'            => Customer::factory(),
            'reference'              => sprintf('BC-%s-%05d', $year, $seq),
            'quote_id'               => null,
            'status'                 => 'draft',
            'order_date'             => $orderDate,
            'expected_delivery_date' => $this->faker->optional()->dateTimeBetween($orderDate, '+30 days'),
            'delivery_address'       => $this->faker->optional()->address(),
            'currency_id'            => null,
            'payment_term_id'        => null,
            'subtotal_ht'            => 0.00,
            'total_discount'         => 0.00,
            'total_tax'              => 0.00,
            'total_ttc'              => 0.00,
            'amount_invoiced'        => 0.00,
            'notes'                  => $this->faker->optional()->sentence(),
            'terms_conditions'       => null,
            'created_by'             => null,
            'confirmed_by'           => null,
            'confirmed_at'           => null,
            'cancelled_by'           => null,
            'cancelled_at'           => null,
            'cancellation_reason'    => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'in_progress',
            'confirmed_at' => now()->subDays(2),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'delivered',
            'confirmed_at' => now()->subDays(5),
        ]);
    }

    public function invoiced(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'invoiced',
            'confirmed_at' => now()->subDays(7),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'              => 'cancelled',
            'cancelled_at'        => now(),
            'cancellation_reason' => $this->faker->sentence(),
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
