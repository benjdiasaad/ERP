<?php

declare(strict_types=1);

namespace Database\Factories\Personnel;

use App\Models\Company\Company;
use App\Models\Personnel\Leave;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leave>
 */
class LeaveFactory extends Factory
{
    protected $model = Leave::class;

    public function definition(): array
    {
        $startDate  = $this->faker->dateTimeBetween('-1 year', '+1 month');
        $totalDays  = $this->faker->randomElement([1, 2, 3, 5, 7, 10, 14]);
        $endDate    = (clone $startDate)->modify("+{$totalDays} days");

        return [
            'company_id'       => Company::factory(),
            'personnel_id'     => Personnel::factory(),
            'leave_type'       => $this->faker->randomElement(['annual', 'sick', 'maternity', 'paternity', 'unpaid', 'compensatory']),
            'start_date'       => $startDate,
            'end_date'         => $endDate,
            'total_days'       => $totalDays,
            'reason'           => $this->faker->optional()->sentence(),
            'status'           => 'pending',
            'approved_by'      => null,
            'approved_at'      => null,
            'rejection_reason' => null,
            'notes'            => $this->faker->optional()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => 'rejected',
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }
}
