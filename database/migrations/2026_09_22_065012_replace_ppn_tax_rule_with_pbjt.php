<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hotel and restaurant sales in this regency are not subject to PPN; they carry
 * PBJT at 10% instead. The rule row is rewritten in place so folio items already
 * referencing the tax keep a single active output-tax rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tax_rules')->where('code', 'ppn')->delete();

        DB::table('tax_rules')->updateOrInsert(
            ['code' => 'pbjt'],
            [
                'name' => 'PBJT',
                'rate_percent' => 10.00,
                'applies_to' => 'all',
                'is_compounding' => true,
                'is_active' => true,
                'order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('tax_rules')->where('code', 'pbjt')->delete();

        DB::table('tax_rules')->updateOrInsert(
            ['code' => 'ppn'],
            [
                'name' => 'PPN',
                'rate_percent' => 11.00,
                'applies_to' => 'all',
                'is_compounding' => true,
                'is_active' => true,
                'order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
