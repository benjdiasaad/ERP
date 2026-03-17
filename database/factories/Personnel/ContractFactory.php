<?php

declare(strict_types=1);

namespace Database\Factories\Personnel;

use App\Models\Company\Company;
use App\Models\Personnel\Contract;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-3 years', 'now');
        $type      = $this->faker->randomElement(['CDI', 'CDD', 'stage', 'freelance', 'interim']);
        $endDate   = $type === 'CDI' ? null : $this->faker->dateTimeBetween($startDate, '+2 years');

        return [
            'company_id'              => Company::factory(),
            'personnel_id'            => Personnel::factory(),
            'reference'               => strtoupper($this->faker->unique()->bothify('CTR-####-??')),
            'type'                    => $type,
            'status'                  => 'active',
            'start_date'              => $startDate,
            'end_date'                => $endDate,
            'trial_period_end_date'   => $this->faker->optional()->dateTimeBetween($startDate, '+3 months'),
            'salary'                  => $this->faker->numberBetween(3000, 15000),
            'salary_currency'         => 'MAD',
            'working_hours_per_week'  => $this->faker->randomElement([35, 40, 45]),
            'benefits'                => null,
            'document_path'           => null,
            'notes'                   => $this->faker->optional()->sentence(),
            'signed_at'               => $this->faker->optional()->dateTimeBetween($startDate, 'now'),
            'terminated_at'           => null,
            'termination_reason'      => null,
            'created_by'              => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'signed_at' => null,
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'             => 'terminated',
            'terminated_at'      => $this->faker->dateTimeBetween('-6 months', 'now'),
            'termination_reason' => $this->faker->sentence(),
        ]);
    }
}
