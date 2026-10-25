<?php

namespace Database\Factories\CRM;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\CRM\Activity;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        return [
            'company_id'  => Company::factory(),
            'type'        => $this->faker->randomElement(Activity::TYPES),
            'subject'     => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'due_date'    => $this->faker->dateTimeBetween('+1 day', '+1 month'),
            'priority'    => $this->faker->randomElement(Activity::PRIORITIES),
            'status'      => 'pending',
            'created_by'  => User::factory(),
        ];
    }

    public function overdue(): self
    {
        return $this->state(fn(array $attributes) => [
            'due_date' => now()->subDays(2),
            'status'   => 'pending',
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn(array $attributes) => [
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    }
}
