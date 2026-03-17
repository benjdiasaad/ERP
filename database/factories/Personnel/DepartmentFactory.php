<?php

declare(strict_types=1);

namespace Database\Factories\Personnel;

use App\Models\Company\Company;
use App\Models\Personnel\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'company_id'  => Company::factory(),
            'parent_id'   => null,
            'name'        => $this->faker->unique()->words(2, true),
            'code'        => strtoupper($this->faker->unique()->bothify('DEPT-###')),
            'description' => $this->faker->optional()->sentence(),
            'manager_id'  => null,
            'is_active'   => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
