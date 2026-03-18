<?php

declare(strict_types=1);

namespace Database\Factories\Purchasing;

use App\Models\Company\Company;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\ReceptionNote;
use App\Models\Purchasing\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReceptionNoteFactory extends Factory
{
    protected $model = ReceptionNote::class;

    public function definition(): array
    {
        return [
            'company_id'        => Company::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'supplier_id'       => Supplier::factory(),
            'reference'         => 'BR-' . now()->year . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'status'            => 'draft',
            'reception_date'    => now()->toDateString(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function confirmed(): static
    {
        return $this->state([
            'status'       => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
