<?php

namespace Database\Seeders;

use App\Models\Finance\Tax;
use Illuminate\Database\Seeder;

class TaxSeeder extends Seeder
{
    public function run(): void
    {
        $taxes = [
            ['name' => 'TVA 20%', 'rate' => 20.0, 'description' => 'Standard VAT rate'],
            ['name' => 'TVA 14%', 'rate' => 14.0, 'description' => 'Reduced VAT rate'],
            ['name' => 'TVA 10%', 'rate' => 10.0, 'description' => 'Reduced VAT rate'],
            ['name' => 'TVA 7%', 'rate' => 7.0, 'description' => 'Reduced VAT rate'],
            ['name' => 'Exempt', 'rate' => 0.0, 'description' => 'Tax exempt'],
        ];

        foreach ($taxes as $tax) {
            Tax::firstOrCreate(
                ['name' => $tax['name']],
                $tax
            );
        }
    }
}
