<?php

declare(strict_types=1);

namespace Database\Factories\Personnel;

use App\Models\Company\Company;
use App\Models\Personnel\Department;
use App\Models\Personnel\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        $salaryMin = $this->faker->numberBetween(3000, 8000);

        return [
            'company_id'    => Company::factory(),
            'department_id' => Department::factory(),
            'name'          => $this->faker->jobTitle(),
            'code'          => strtoupper($this->faker->unique()->bothify('POS-###')),
            'description'   => $this->faker->optional()->sentence(),
            'salary_min'    => $salaryMin,
            'salary_max'    => $this->faker->numberBetween($salaryMin, $salaryMin + 5000),
            'is_active'     => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
