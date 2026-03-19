<?php

namespace Database\Factories;

use App\Models\Caution\Caution;
use App\Models\Caution\CautionHistory;
use App\Models\Company\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

class CautionHistoryFactory extends Factory
{
    protected $model = CautionHistory::class;

    public function definition(): array
    {
        return [
            'company_id'        => Company::factory(),
            'caution_id'        => Caution::factory(),
            'action'            => $this->faker->randomElement(['created', 'updated', 'activated', 'partial_return', 'full_return', 'extended', 'forfeited', 'cancelled']),
            'amount'            => $this->faker->randomFloat(2, 1000, 100000),
            'previous_status'   => $this->faker->randomElement(['draft', 'active', 'partially_returned', 'returned', 'expired', 'forfeited']),
            'new_status'        => $this->faker->randomElement(['draft', 'active', 'partially_returned', 'returned', 'expired', 'forfeited']),
            'notes'             => $this->faker->optional()->sentence(),
            'created_by'        => $this->faker->numberBetween(1, 100),
        ];
    }
}
