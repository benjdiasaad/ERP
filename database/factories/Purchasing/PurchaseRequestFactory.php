<?php

declare(strict_types=1);

namespace Database\Factories\Purchasing;

use App\Models\Company\Company;
use App\Models\Purchasing\PurchaseRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequest>
 */
class PurchaseRequestFactory extends Factory
{
    protected $model = PurchaseRequest::class;

    public function definition(): array
    {
        $year = now()->format('Y');
        $seq  = $this->faker->unique()->numberBetween(1, 99999);

        return [
            'company_id'       => Company::factory(),
            'reference'        => sprintf('DA-%s-%05d', $year, $seq),
            'supplier_id'      => null,
            'requested_by'     => null,
            'approved_by'      => null,
            'rejected_by'      => null,
            'title'            => $this->faker->sentence(4),
            'description'      => $this->faker->optional()->paragraph(),
            'priority'         => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'status'           => 'draft',
            'required_date'    => $this->faker->optional()->dateTimeBetween('now', '+30 days'),
            'notes'            => $this->faker->optional()->sentence(),
            'rejection_reason' => null,
            'submitted_at'     => null,
            'approved_at'      => null,
            'rejected_at'      => null,
            'created_by'       => null,
            'updated_by'       => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => 'approved',
            'submitted_at' => now()->subDays(2),
            'approved_at'  => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => 'rejected',
            'submitted_at'     => now()->subDays(2),
            'rejected_at'      => now(),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'cancelled']);
    }
}
