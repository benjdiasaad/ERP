<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Sales\DeliveryNote;
use App\Models\Sales\DeliveryNoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryNoteLine>
 */
class DeliveryNoteLineFactory extends Factory
{
    protected $model = DeliveryNoteLine::class;

    public function definition(): array
    {
        return [
            'delivery_note_id'    => DeliveryNote::factory(),
            'sales_order_line_id' => null,
            'product_id'          => null,
            'description'         => $this->faker->sentence(),
            'ordered_quantity'    => $this->faker->randomFloat(2, 1, 100),
            'shipped_quantity'    => 0.00,
            'returned_quantity'   => 0.00,
            'unit'                => 'pcs',
            'sort_order'          => 0,
            'notes'               => null,
        ];
    }
}
