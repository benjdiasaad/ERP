<?php

declare(strict_types=1);

namespace Database\Factories\Sales;

use App\Models\Company\Company;
use App\Models\Sales\CreditNote;
use App\Models\Sales\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    protected $model = CreditNote::class;

    public function definition(): array
    {
        $year = now()->format('Y');
        $seq  = $this->faker->unique()->numberBetween(1, 99999);

        return [
            'company_id'     => Company::factory(),
            'customer_id'    => Customer::factory(),
            'invoice_id'     => null,
            'reference'      => sprintf('AV-%s-%05d', $year, $seq),
            'status'         => 'draft',
            'date'           => $this->faker->date(),
            'reason'         => $this->faker->sentence(),
            'subtotal_ht'    => 0.00,
            'total_discount' => 0.00,
            'total_tax'      => 0.00,
            'total_ttc'      => 0.00,
            'notes'          => null,
            'created_by'     => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function applied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'applied',
            'confirmed_at' => now()->subDay(),
            'applied_at'   => now(),
        ]);
    }
}
