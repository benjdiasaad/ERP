<?php

declare(strict_types=1);

namespace Database\Factories\Personnel;

use App\Models\Company\Company;
use App\Models\Personnel\Department;
use App\Models\Personnel\Personnel;
use App\Models\Personnel\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Personnel>
 */
class PersonnelFactory extends Factory
{
    protected $model = Personnel::class;

    public function definition(): array
    {
        $year     = now()->format('Y');
        $sequence = $this->faker->unique()->numberBetween(1, 99999);
        $hireDate = $this->faker->dateTimeBetween('-5 years', 'now');

        return [
            'company_id'                  => Company::factory(),
            'user_id'                     => null,
            'department_id'               => null,
            'position_id'                 => null,
            'matricule'                   => sprintf('PER-%s-%05d', $year, $sequence),
            'first_name'                  => $this->faker->firstName(),
            'last_name'                   => $this->faker->lastName(),
            'email'                       => $this->faker->optional()->safeEmail(),
            'phone'                       => $this->faker->optional()->phoneNumber(),
            'mobile'                      => $this->faker->optional()->phoneNumber(),
            'gender'                      => $this->faker->optional()->randomElement(['male', 'female', 'other']),
            'birth_date'                  => $this->faker->optional()->dateTimeBetween('-60 years', '-18 years'),
            'birth_place'                 => $this->faker->optional()->city(),
            'nationality'                 => $this->faker->optional()->country(),
            'national_id'                 => $this->faker->optional()->numerify('##########'),
            'social_security_number'      => $this->faker->optional()->numerify('###-##-####'),
            'address'                     => $this->faker->optional()->streetAddress(),
            'city'                        => $this->faker->optional()->city(),
            'country'                     => 'MA',
            'photo_path'                  => null,
            'employment_type'             => $this->faker->randomElement(['full_time', 'part_time', 'freelance', 'intern']),
            'hire_date'                   => $hireDate,
            'termination_date'            => null,
            'status'                      => 'active',
            'bank_name'                   => $this->faker->optional()->company(),
            'bank_account'                => $this->faker->optional()->numerify('##########'),
            'bank_iban'                   => $this->faker->optional()->iban(),
            'emergency_contact_name'      => $this->faker->optional()->name(),
            'emergency_contact_phone'     => $this->faker->optional()->phoneNumber(),
            'emergency_contact_relation'  => $this->faker->optional()->randomElement(['spouse', 'parent', 'sibling', 'friend']),
            'notes'                       => $this->faker->optional()->sentence(),
            'created_by'                  => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'termination_date' => null,
        ]);
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'terminated',
            'termination_date' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function withDepartmentAndPosition(): static
    {
        return $this->state(function (array $attributes) {
            $department = Department::factory()->create(['company_id' => $attributes['company_id']]);
            $position   = Position::factory()->create([
                'company_id'    => $attributes['company_id'],
                'department_id' => $department->id,
            ]);

            return [
                'department_id' => $department->id,
                'position_id'   => $position->id,
            ];
        });
    }
}
