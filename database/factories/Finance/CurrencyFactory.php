<?php

declare(strict_types=1);

namespace Database\Factories\Finance;

use App\Models\Company\Company;
use App\Models\Finance\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        $codes = ['MAD', 'EUR', 'USD', 'GBP', 'JPY', 'CHF', 'CAD', 'AUD', 'CNY', 'INR', 'BRL', 'MXN', 'SGD', 'HKD', 'AED', 'SAR'];
        $symbols = ['د.م.', '€', '$', '£', '¥', 'CHF', 'C$', 'A$', '¥', '₹', 'R$', '$', 'S$', 'HK$', 'د.إ', 'ر.س'];
        
        $index = self::$sequence % count($codes);
        self::$sequence++;

        return [
            'company_id'    => Company::factory(),
            'code'          => $codes[$index],
            'name'          => $this->faker->currencyCode(),
            'symbol'        => $symbols[$index],
            'exchange_rate' => $this->faker->randomFloat(6, 0.5, 2.0),
            'is_default'    => false,
            'is_active'     => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function mad(): static
    {
        return $this->state(fn (array $attributes) => [
            'code'          => 'MAD',
            'name'          => 'Moroccan Dirham',
            'symbol'        => 'د.م.',
            'exchange_rate' => 1.0,
        ]);
    }

    public function eur(): static
    {
        return $this->state(fn (array $attributes) => [
            'code'          => 'EUR',
            'name'          => 'Euro',
            'symbol'        => '€',
            'exchange_rate' => 10.5,
        ]);
    }

    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'code'          => 'USD',
            'name'          => 'US Dollar',
            'symbol'        => '$',
            'exchange_rate' => 9.8,
        ]);
    }

    public function gbp(): static
    {
        return $this->state(fn (array $attributes) => [
            'code'          => 'GBP',
            'name'          => 'British Pound',
            'symbol'        => '£',
            'exchange_rate' => 12.3,
        ]);
    }
}
