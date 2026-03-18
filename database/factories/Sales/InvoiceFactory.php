<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Company\Company;
use App\Models\Sales\Customer;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $year        = now()->format('Y');
        $seq         = $this->faker->unique()->numberBetween(1, 99999);
        $invoiceDate = $this->faker->dateTimeBetween('-6 months', 'now');

        return [
            'company_id'      => Company::factory(),
            'customer_id'     => Customer::factory(),
            'sales_order_id'  => null,
            'reference'       => sprintf('FAC-%s-%05d', $year, $seq),
            'status'          => 'draft',
            'invoice_date'    => $invoiceDate,
            'due_date'        => $this->faker->optional()->dateTimeBetween($invoiceDate, '+30 days'),
            'payment_term_id' => null,
            'currency_id'     => null,
            'subtotal_ht'     => 0.00,
            'total_discount'  => 0.00,
            'total_tax'       => 0.00,
            'total_ttc'       => 0.00,
            'amount_paid'     => 0.00,
            'amount_due'      => 0.00,
            'notes'           => $this->faker->optional()->sentence(),
            'terms'           => null,
            'created_by'      => null,
            'updated_by'      => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'sent']);
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => 'partial',
            'amount_paid' => $this->faker->randomFloat(2, 10, 500),
        ]);
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            $total = $attributes['total_ttc'] ?? 1000.00;
            return [
                'status'      => 'paid',
                'amount_paid' => $total,
                'amount_due'  => 0.00,
            ];
        });
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'   => 'overdue',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'cancelled']);
    }

    public function withTotals(float $subtotalHt, float $totalTax = 0, float $totalDiscount = 0): static
    {
        return $this->state(function (array $attributes) use ($subtotalHt, $totalTax, $totalDiscount) {
            $totalTtc = $subtotalHt - $totalDiscount + $totalTax;
            return [
                'subtotal_ht'    => $subtotalHt,
                'total_discount' => $totalDiscount,
                'total_tax'      => $totalTax,
                'total_ttc'      => $totalTtc,
                'amount_due'     => $totalTtc - ($attributes['amount_paid'] ?? 0),
            ];
        });
    }
}
