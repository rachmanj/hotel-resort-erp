<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\TaxRule;
use Illuminate\Database\Seeder;

class BillingDemoSeeder extends Seeder
{
    public function run(): void
    {
        TaxRule::query()->updateOrCreate(
            ['code' => 'service_charge'],
            [
                'name' => 'Service Charge',
                'rate_percent' => 10.00,
                'applies_to' => 'all',
                'is_compounding' => false,
                'is_active' => true,
                'order' => 1,
            ],
        );

        TaxRule::query()->updateOrCreate(
            ['code' => 'ppn'],
            [
                'name' => 'PPN',
                'rate_percent' => 11.00,
                'applies_to' => 'all',
                'is_compounding' => true,
                'is_active' => true,
                'order' => 2,
            ],
        );

        Company::query()->updateOrCreate(
            ['name' => 'PT Maju Bersama Indonesia'],
            [
                'tax_id' => '01.234.567.8-901.000',
                'billing_address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
                'phone' => '+62-21-5551234',
                'email' => 'billing@majubersama.co.id',
                'credit_limit' => 50000000.00,
                'payment_terms_days' => 30,
                'is_active' => true,
            ],
        );

        Company::query()->updateOrCreate(
            ['name' => 'CV Nusantara Travel'],
            [
                'tax_id' => '02.345.678.9-012.000',
                'billing_address' => 'Jl. Gatot Subroto No. 45, Jakarta Selatan',
                'phone' => '+62-21-5559876',
                'email' => 'finance@nusantaratravel.com',
                'credit_limit' => 25000000.00,
                'payment_terms_days' => 14,
                'is_active' => true,
            ],
        );
    }
}
