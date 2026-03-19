<?php

declare(strict_types=1);

namespace Database\Factories\Finance;

use App\Models\Company\Company;
use App\Models\Finance\JournalEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    public function definition(): array
    {
        return [
            'company_id'   => Company::factory(),
            'reference'    => $this->faker->unique()->numerify('JE-####'),
            'date'         => $this->faker->dateTime(),
            'description'  => $this->faker->sentence(),
            'status'       => 'draft',
            'total_debit'  => 0,
            'total_credit' => 0,
            'posted_at'    => null,
            'posted_by'    => null,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'    => 'posted',
            'posted_at' => now(),
            'posted_by' => 1,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'    => 'draft',
            'posted_at' => null,
            'posted_by' => null,
        ]);
    }

    public function withCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => ['company_id' => $company->id]);
    }
}
