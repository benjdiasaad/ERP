<?php

declare(strict_types=1);

namespace Database\Factories\Finance;

use App\Models\Finance\ChartOfAccount;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalEntryLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntryLine>
 */
class JournalEntryLineFactory extends Factory
{
    protected $model = JournalEntryLine::class;

    public function definition(): array
    {
        $debit = $this->faker->boolean() ? $this->faker->randomFloat(2, 0.01, 10000) : 0;
        $credit = $debit === 0 ? $this->faker->randomFloat(2, 0.01, 10000) : 0;

        return [
            'journal_entry_id'      => JournalEntry::factory(),
            'chart_of_account_id'   => ChartOfAccount::factory(),
            'debit'                 => $debit,
            'credit'                => $credit,
            'description'           => $this->faker->optional()->sentence(),
        ];
    }

    public function debit(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'debit'  => $amount,
            'credit' => 0,
        ]);
    }

    public function credit(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'debit'  => 0,
            'credit' => $amount,
        ]);
    }

    public function withAccount(ChartOfAccount $account): static
    {
        return $this->state(fn (array $attributes) => ['chart_of_account_id' => $account->id]);
    }

    public function withJournalEntry(JournalEntry $entry): static
    {
        return $this->state(fn (array $attributes) => ['journal_entry_id' => $entry->id]);
    }
}
