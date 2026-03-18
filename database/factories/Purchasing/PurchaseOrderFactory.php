<?php

declare(strict_types=1);

namespace Database\Factories\Purchasing;

use App\Models\Company\Company;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'company_id'  => Company::factory(),
            'supplier_id' => Supplier::factory(),
            'reference'   => 'PO-' . now()->year . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'status'      => 'draft',
            'order_date'  => now()->toDateString(),
            'subtotal_ht' => 0,
            'total_discount' => 0,
            'total_tax'   => 0,
            'total_ttc'   => 0,
            'amount_received' => 0,
            'amount_invoiced' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function sent(): static
    {
        return $this->state(['status' => 'sent', 'sent_at' => now()]);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => 'confirmed', 'sent_at' => now(), 'confirmed_at' => now()]);
    }

    public function inProgress(): static
    {
        return $this->state(['status' => 'in_progress', 'sent_at' => now(), 'confirmed_at' => now()]);
    }

    public function received(): static
    {
        return $this->state(['status' => 'received', 'sent_at' => now(), 'confirmed_at' => now()]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled', 'cancelled_at' => now()]);
    }
}
