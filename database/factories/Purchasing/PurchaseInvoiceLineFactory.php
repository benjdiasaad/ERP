<?php

declare(strict_types=1);

namespace Database\Factories\Purchasing;

use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Purchasing\PurchaseInvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseInvoiceLineFactory extends Factory
{
    protected $model = PurchaseInvoiceLine::class;

    public function definition(): array
    {
        $quantity    = $this->faker->numberBetween(1, 10);
        $unitPrice   = $this->faker->randomFloat(2, 10, 500);
        $subtotalHt  = round($quantity * $unitPrice, 2);
        $taxRate     = 20.0;
        $taxAmount   = round($subtotalHt * ($taxRate / 100), 2);
        $totalTtc    = round($subtotalHt + $taxAmount, 2);

        return [
            'purchase_invoice_id'        => PurchaseInvoice::factory(),
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
            'sort_order'                 => 0,
        ];
    }
}
