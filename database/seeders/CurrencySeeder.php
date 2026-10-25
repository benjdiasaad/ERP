<?php

namespace Database\Seeders;

use App\Models\Company\Company;
use App\Models\Finance\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    private const CURRENCIES = [
        ['code' => 'MAD', 'symbol' => 'د.م.', 'name' => 'Moroccan Dirham', 'exchange_rate' => 1.0],
        ['code' => 'EUR', 'symbol' => '€', 'name' => 'Euro', 'exchange_rate' => 10.5],
        ['code' => 'USD', 'symbol' => '$', 'name' => 'US Dollar', 'exchange_rate' => 9.8],
        ['code' => 'GBP', 'symbol' => '£', 'name' => 'British Pound', 'exchange_rate' => 12.3],
    ];

    /**
     * Currencies are company-scoped (BelongsToCompany), so each company gets its own set.
     */
    public function run(): void
    {
        Company::query()->each(fn (Company $company) => $this->seedForCompany($company));
    }

    public function seedForCompany(Company $company): void
    {
        foreach (self::CURRENCIES as $currency) {
            Currency::withoutGlobalScopes()->firstOrCreate(
                ['company_id' => $company->id, 'code' => $currency['code']],
                $currency
            );
        }
    }
}
