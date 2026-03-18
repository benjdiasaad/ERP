<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Company\Company;
use App\Models\Sales\Customer;
use App\Models\Sales\DeliveryNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryNote>
 */
class DeliveryNoteFactory extends Factory
{
    protected $model = DeliveryNote::class;

    public function definition(): array
    {
        $year = now()->format('Y');
        $seq  = $this->faker->unique()->numberBetween(1, 99999);

        return [
            'company_id'             => Company::factory(),
            'customer_id'            => Customer::factory(),
            'sales_order_id'         => null,
            'reference'              => sprintf('BL-%s-%05d', $year, $seq),
            'status'                 => 'draft',
            'date'                   => $this->faker->date(),
            'expected_delivery_date' => $this->faker->optional()->date(),
            'delivery_address'       => $this->faker->address(),
            'carrier'                => null,
            'tracking_number'        => null,
            'notes'                  => null,
            'created_by'             => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'ready']);
    }

    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'     => 'shipped',
            'shipped_at' => now()->toDateString(),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'delivered',
            'shipped_at'   => now()->subDay()->toDateString(),
            'delivered_at' => now()->toDateString(),
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'returned']);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'cancelled']);
    }
}
