<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Auth\User;
use App\Models\Company\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $year     = now()->format('Y');
        $sequence = $this->faker->unique()->numberBetween(1, 99999);

        $firstName = $this->faker->firstName();
        $lastName  = $this->faker->lastName();

        return [
            'matricule'  => sprintf('EMP-%s-%05d', $year, $sequence),
            'name'       => $firstName . ' ' . $lastName,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('Password1!'),
            'phone' => $this->faker->phoneNumber(),
            'avatar_path' => null,
            'current_company_id' => null,
            'is_active' => true,
            'last_login_at' => null,
            'last_login_ip' => null,
            'password_changed_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => [
            'current_company_id' => $company->id,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
