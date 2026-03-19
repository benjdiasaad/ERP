<?php

namespace Database\Factories\Event;

use App\Models\Company\Company;
use App\Models\Event\EventCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventCategoryFactory extends Factory
{
    protected $model = EventCategory::class;

    public function definition(): array
    {
        return [
            'company_id'  => Company::factory(),
            'name'        => $this->faker->words(2, true),
            'color'       => $this->faker->hexColor(),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
