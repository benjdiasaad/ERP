<?php

declare(strict_types=1);

namespace Database\Factories\Purchasing;

use App\Models\Company\Company;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderLineFactory extends Factory
{
    protected $model = PurchaseOrderLine::class;

    public function definition(): array
    {
        $quantity    = $this->faker->randomFloat(2, 1, 100);
        $unitPrice   = $this->faker->randomFloat(2, 10, 1000);
        $subtotalHt  = round($quantity * $unitPrice, 2);
        $taxRate     = 20.0;
        $taxAmount   = round($subtotalHt * ($taxRate / 100), 2);
        $totalTtc    = round($subtotalHt + $taxAmount, 2);

        return [
            'company_id'                 => Company::factory(),
            'purchase_order_id'          => PurchaseOrder::factory(),
            'description'                => $this->faker->words(3, true),
            'quantity'                   => $quantity,
            'unit_price_ht'              => $unitPrice,
            'discount_type'              => 'percentage',
            'discount_value'             => 0,
            'subtotal_ht'                => $subtotalHt,
            'discount_amount'            => 0,
            'subtotal_ht_after_discount' => $subtotalHt,
            'tax_rate'                   => $taxRate,
            'tax_amount'                 => $taxAmount,
            'total_ttc'                  => $totalTtc,
            'received_quantity'          => 0,
            'invoiced_quantity'          => 0,
            'sort_order'                 => 0,
        ];
    }
}
