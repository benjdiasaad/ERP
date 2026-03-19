<?php

namespace Database\Seeders;

use App\Models\Finance\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'MAD', 'symbol' => 'د.م.', 'name' => 'Moroccan Dirham', 'exchange_rate' => 1.0],
            ['code' => 'EUR', 'symbol' => '€', 'name' => 'Euro', 'exchange_rate' => 10.5],
            ['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 9.8],
            ['code' => 'GBP', 'symbol' => '£', 'name' => 'British Pound', 'exchange_rate' => 12.3],
        ];

        foreach ($currencies as $currency) {
            Currency::firstOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
