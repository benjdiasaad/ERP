<?php

namespace Database\Factories;

use App\Models\Caution\CautionType;
use App\Models\Company\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CautionTypeFactory extends Factory
{
    protected $model = CautionType::class;

    public function definition(): array
    {
        return [
            'company_id'           => Company::factory(),
            'name'                 => $this->faker->word(),
            'description'          => $this->faker->sentence(),
            'default_percentage'   => $this->faker->numberBetween(5, 50),
        ];
    }
}
