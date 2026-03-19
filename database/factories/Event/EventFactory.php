<?php

namespace Database\Factories\Event;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\Event\Event;
use App\Models\Event\EventCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 month', '+3 months');
        $endDate = $this->faker->dateTimeBetween($startDate, '+1 week');

        return [
            'company_id'        => Company::factory(),
            'event_category_id' => EventCategory::factory(),
            'title'             => $this->faker->sentence(4),
            'type'              => $this->faker->randomElement(['meeting', 'conference', 'training', 'workshop', 'social', 'holiday']),
            'location'          => $this->faker->optional()->address(),
            'description'       => $this->faker->optional()->paragraph(),
            'start_date'        => $startDate,
            'end_date'          => $endDate,
            'budget'            => $this->faker->optional()->randomFloat(2, 1000, 50000),
            'is_mandatory'      => $this->faker->boolean(30),
            'recurring_pattern' => null,
            'status'            => $this->faker->randomElement(['planned', 'confirmed', 'in_progress', 'completed', 'cancelled', 'postponed']),
            'created_by'        => User::factory(),
        ];
    }

    public function planned(): self
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'planned',
        ]);
    }

    public function confirmed(): self
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    public function inProgress(): self
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'completed',
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    public function mandatory(): self
    {
        return $this->state(fn(array $attributes) => [
            'is_mandatory' => true,
        ]);
    }

    public function withRecurring(): self
    {
        return $this->state(fn(array $attributes) => [
            'recurring_pattern' => [
                'frequency' => $this->faker->randomElement(['daily', 'weekly', 'monthly', 'yearly']),
                'interval' => $this->faker->numberBetween(1, 4),
                'end_date' => $this->faker->optional()->dateTimeBetween('+1 month', '+1 year')?->format('Y-m-d'),
            ],
        ]);
    }
}
