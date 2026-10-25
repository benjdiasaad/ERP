<?php

namespace Database\Factories\CRM;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\CRM\Opportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    public function definition(): array
    {
        return [
            'company_id'          => Company::factory(),
            'reference'           => 'OPP-' . date('Y') . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'title'               => $this->faker->sentence(4),
            'description'         => $this->faker->optional()->paragraph(),
            'value'               => $this->faker->randomFloat(2, 1000, 100000),
            'probability'         => Opportunity::STAGE_PROBABILITIES['prospecting'],
            'stage'               => 'prospecting',
            'expected_close_date' => $this->faker->dateTimeBetween('now', '+6 months'),
            'created_by'          => User::factory(),
        ];
    }

    public function stage(string $stage): self
    {
        return $this->state(fn(array $attributes) => [
            'stage'       => $stage,
            'probability' => Opportunity::STAGE_PROBABILITIES[$stage],
        ]);
    }

    public function won(): self
    {
        return $this->stage('closed_won')->state(fn(array $attributes) => [
            'actual_close_date' => now(),
        ]);
    }

    public function lost(): self
    {
        return $this->stage('closed_lost')->state(fn(array $attributes) => [
            'actual_close_date' => now(),
            'lost_reason'       => $this->faker->sentence(),
        ]);
    }
}
