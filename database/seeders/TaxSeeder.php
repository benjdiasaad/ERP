<?php

namespace Database\Seeders;

use App\Models\Company\Company;
use App\Models\Finance\Tax;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    private const TAXES = [
        ['name' => 'TVA 20%', 'rate' => 20.0, 'description' => 'Standard VAT rate'],
        ['name' => 'TVA 14%', 'rate' => 14.0, 'description' => 'Reduced VAT rate'],
        ['name' => 'TVA 10%', 'rate' => 10.0, 'description' => 'Reduced VAT rate'],
        ['name' => 'TVA 7%', 'rate' => 7.0, 'description' => 'Reduced VAT rate'],
        ['name' => 'Exempt', 'rate' => 0.0, 'description' => 'Tax exempt'],
    ];

    /**
     * Taxes are company-scoped (BelongsToCompany), so each company gets its own set.
     */
    public function run(): void
    {
        Company::query()->each(fn (Company $company) => $this->seedForCompany($company));
    }

    public function seedForCompany(Company $company): void
    {
        foreach (self::TAXES as $tax) {
            Tax::withoutGlobalScopes()->firstOrCreate(
                ['company_id' => $company->id, 'name' => $tax['name']],
                $tax
            );
        }
    }
}
