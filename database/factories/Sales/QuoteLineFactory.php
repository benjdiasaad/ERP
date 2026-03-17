<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Company\Company;
use App\Models\Sales\Quote;
use App\Models\Sales\QuoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLine>
 */
class QuoteLineFactory extends Factory
{
    protected $model = QuoteLine::class;

    public function definition(): array
    {
        $quantity    = $this->faker->randomFloat(2, 1, 100);
        $unitPrice   = $this->faker->randomFloat(2, 10, 10000);
        $subtotalHt  = round($quantity * $unitPrice, 2);
        $taxRate     = $this->faker->randomElement([0, 7, 10, 14, 20]);
        $taxAmount   = round($subtotalHt * ($taxRate / 100), 2);
        $totalTtc    = round($subtotalHt + $taxAmount, 2);

        return [
            'company_id'                 => Company::factory(),
            'quote_id'                   => Quote::factory(),
            'product_id'                 => null,
            'description'                => $this->faker->words(4, true),
            'quantity'                   => $quantity,
            'unit_price_ht'              => $unitPrice,
            'discount_type'              => null,
            'discount_value'             => 0.00,
            'subtotal_ht'                => $subtotalHt,
            'discount_amount'            => 0.00,
            'subtotal_ht_after_discount' => $subtotalHt,
            'tax_id'                     => null,
            'tax_rate'                   => $taxRate,
            'tax_amount'                 => $taxAmount,
            'total_ttc'                  => $totalTtc,
            'sort_order'                 => $this->faker->numberBetween(0, 100),
        ];
    }

    public function withPercentageDiscount(float $percent): static
    {
        return $this->state(function (array $attributes) use ($percent) {
            $discountAmount = round($attributes['subtotal_ht'] * ($percent / 100), 2);
            $subtotalAfter  = round($attributes['subtotal_ht'] - $discountAmount, 2);
            $taxAmount      = round($subtotalAfter * ($attributes['tax_rate'] / 100), 2);

            return [
                'discount_type'              => 'percentage',
                'discount_value'             => $percent,
                'discount_amount'            => $discountAmount,
                'subtotal_ht_after_discount' => $subtotalAfter,
                'tax_amount'                 => $taxAmount,
                'total_ttc'                  => round($subtotalAfter + $taxAmount, 2),
            ];
        });
    }

    public function withFixedDiscount(float $amount): static
    {
        return $this->state(function (array $attributes) use ($amount) {
            $subtotalAfter = round($attributes['subtotal_ht'] - $amount, 2);
            $taxAmount     = round($subtotalAfter * ($attributes['tax_rate'] / 100), 2);

            return [
                'discount_type'              => 'fixed',
                'discount_value'             => $amount,
                'discount_amount'            => $amount,
                'subtotal_ht_after_discount' => $subtotalAfter,
                'tax_amount'                 => $taxAmount,
                'total_ttc'                  => round($subtotalAfter + $taxAmount, 2),
            ];
        });
    }

    public function noTax(): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_id'     => null,
            'tax_rate'   => 0,
            'tax_amount' => 0,
            'total_ttc'  => $attributes['subtotal_ht_after_discount'],
        ]);
    }
}
