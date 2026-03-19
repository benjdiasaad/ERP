<?php

declare(strict_types=1);

namespace Database\Factories\Purchasing;

use App\Models\Purchasing\PurchaseInvoice;
use App\Models\Purchasing\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseInvoiceFactory extends Factory
{
    protected $model = PurchaseInvoice::class;

    public function definition(): array
    {
        return [
            'reference'    => 'FAF-' . now()->year . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'supplier_id'  => Supplier::factory(),
            'status'       => 'draft',
            'invoice_date' => now()->toDateString(),
            'due_date'     => now()->addDays(30)->toDateString(),
            'subtotal_ht'  => 0,
            'total_discount' => 0,
            'total_tax'    => 0,
            'total_ttc'    => 0,
            'amount_paid'  => 0,
            'amount_due'   => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function sent(): static
    {
        return $this->state(['status' => 'sent']);
    }

    public function partial(): static
    {
        return $this->state(fn (array $attrs) => [
            'status'      => 'partial',
            'total_ttc'   => 1000,
            'amount_paid' => 400,
            'amount_due'  => 600,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attrs) => [
            'status'      => 'paid',
            'total_ttc'   => 1000,
            'amount_paid' => 1000,
            'amount_due'  => 0,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
