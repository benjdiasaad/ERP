<?php

declare(strict_types=1);

namespace Database\Factories\Finance;

use App\Models\Company\Company;
use App\Models\Finance\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChartOfAccount>
 */
class ChartOfAccountFactory extends Factory
{
    protected $model = ChartOfAccount::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        $types = ['asset', 'liability', 'equity', 'revenue', 'expense'];
        $type = $types[self::$sequence % count($types)];
        self::$sequence++;

        return [
            'company_id'  => Company::factory(),
            'parent_id'   => null,
            'code'        => $this->faker->unique()->numerify('###-###'),
            'name'        => $this->faker->words(3, true),
            'type'        => $type,
            'description' => $this->faker->optional()->sentence(),
            'is_active'   => true,
            'balance'     => 0,
        ];
    }

    public function asset(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'asset']);
    }

    public function liability(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'liability']);
    }

    public function equity(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'equity']);
    }

    public function revenue(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'revenue']);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'expense']);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function withBalance(float $balance): static
    {
        return $this->state(fn (array $attributes) => ['balance' => $balance]);
    }

    public function withParent(ChartOfAccount $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id'  => $parent->id,
            'company_id' => $parent->company_id,
        ]);
    }
}
