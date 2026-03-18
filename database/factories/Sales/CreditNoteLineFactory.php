<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\CreditNote;
use App\Models\Sales\CreditNoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNoteLine>
 */
class CreditNoteLineFactory extends Factory
{
    protected $model = CreditNoteLine::class;

    public function definition(): array
    {
        return [
            'credit_note_id' => CreditNote::factory(),
            'product_id'     => null,
            'description'    => $this->faker->sentence(),
            'quantity'       => $this->faker->randomFloat(2, 1, 100),
            'unit_price_ht'  => $this->faker->randomFloat(2, 10, 1000),
            'discount_type'  => 'percentage',
            'discount_value' => 0.00,
            'tax_rate'       => 20.00,
            'subtotal_ht'    => 0.00,
            'tax_amount'     => 0.00,
            'total_ttc'      => 0.00,
            'sort_order'     => 0,
        ];
    }
}
