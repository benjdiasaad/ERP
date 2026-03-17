<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesOrderLine>
 */
class SalesOrderLineFactory extends Factory
{
    protected $model = SalesOrderLine::class;

    public function definition(): array
    {
        $quantity    = $this->faker->randomFloat(2, 1, 100);
        $unitPrice   = $this->faker->randomFloat(2, 10, 1000);
        $subtotalHt  = round($quantity * $unitPrice, 2);
        $taxRate     = $this->faker->randomElement([0, 7, 10, 14, 20]);
        $taxAmount   = round($subtotalHt * ($taxRate / 100), 2);
        $totalTtc    = $subtotalHt + $taxAmount;

        return [
            'company_id'                 => null,
            'sales_order_id'             => SalesOrder::factory(),
            'product_id'                 => null,
            'description'                => $this->faker->words(3, true),
            'quantity'                   => $quantity,
            'unit_price_ht'              => $unitPrice,
            'discount_type'              => 'percentage',
            'discount_value'             => 0,
            'subtotal_ht'                => $subtotalHt,
            'discount_amount'            => 0,
            'subtotal_ht_after_discount' => $subtotalHt,
            'tax_id'                     => null,
            'tax_rate'                   => $taxRate,
            'tax_amount'                 => $taxAmount,
            'total_ttc'                  => $totalTtc,
            'delivered_quantity'         => 0,
            'invoiced_quantity'          => 0,
            'sort_order'                 => 0,
        ];
    }

    public function withDiscount(float $discountValue, string $discountType = 'percentage'): static
    {
        return $this->state(function (array $attributes) use ($discountValue, $discountType) {
            $subtotalHt = (float) $attributes['subtotal_ht'];
            $discountAmount = $discountType === 'percentage'
                ? round($subtotalHt * ($discountValue / 100), 2)
                : $discountValue;
            $subtotalAfter = $subtotalHt - $discountAmount;
            $taxAmount = round($subtotalAfter * ((float) $attributes['tax_rate'] / 100), 2);

            return [
                'discount_type'              => $discountType,
                'discount_value'             => $discountValue,
                'discount_amount'            => $discountAmount,
                'subtotal_ht_after_discount' => $subtotalAfter,
                'tax_amount'                 => $taxAmount,
                'total_ttc'                  => $subtotalAfter + $taxAmount,
            ];
        });
    }
}
