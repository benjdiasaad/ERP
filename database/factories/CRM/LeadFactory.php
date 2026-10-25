<?php

namespace Database\Factories\CRM;

use App\Models\Auth\User;
use App\Models\Company\Company;
use App\Models\CRM\Contact;
use App\Models\CRM\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'company_id'          => Company::factory(),
            'reference'           => 'LD-' . date('Y') . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'contact_id'          => null,
            'title'               => $this->faker->sentence(4),
            'description'         => $this->faker->optional()->paragraph(),
            'source'              => $this->faker->randomElement(Contact::SOURCES),
            'estimated_value'     => $this->faker->randomFloat(2, 1000, 100000),
            'probability'         => $this->faker->numberBetween(0, 100),
            'expected_close_date' => $this->faker->dateTimeBetween('now', '+6 months'),
            'status'              => 'new',
            'created_by'          => User::factory(),
        ];
    }

    public function contacted(): self
    {
        return $this->state(fn(array $attributes) => ['status' => 'contacted']);
    }

    public function qualified(): self
    {
        return $this->state(fn(array $attributes) => [
            'status'       => 'qualified',
            'qualified_at' => now(),
        ]);
    }

    public function converted(): self
    {
        return $this->state(fn(array $attributes) => [
            'status'       => 'converted',
            'converted_at' => now(),
        ]);
    }

    public function lost(): self
    {
        return $this->state(fn(array $attributes) => [
            'status'      => 'lost',
            'lost_reason' => $this->faker->sentence(),
        ]);
    }
}
