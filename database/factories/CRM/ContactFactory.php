<?php

namespace Database\Factories\CRM;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\CRM\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'company_id'   => Company::factory(),
            'first_name'   => $this->faker->firstName(),
            'last_name'    => $this->faker->lastName(),
            'email'        => $this->faker->unique()->safeEmail(),
            'phone'        => $this->faker->optional()->phoneNumber(),
            'mobile'       => $this->faker->optional()->phoneNumber(),
            'job_title'    => $this->faker->optional()->jobTitle(),
            'organization' => $this->faker->optional()->company(),
            'city'         => $this->faker->optional()->city(),
            'country'      => 'MA',
            'source'       => $this->faker->randomElement(Contact::SOURCES),
            'tags'         => null,
            'is_active'    => true,
            'created_by'   => User::factory(),
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn(array $attributes) => [
            'is_active' => false,
        ]);
    }
}
