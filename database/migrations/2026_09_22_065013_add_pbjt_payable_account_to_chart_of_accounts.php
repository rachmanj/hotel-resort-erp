<?php

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hotelIds = DB::table('chart_of_accounts')
            ->whereNotNull('hotel_id')
            ->distinct()
            ->pluck('hotel_id');

        foreach ($hotelIds as $hotelId) {
            $exists = DB::table('chart_of_accounts')
                ->where('hotel_id', $hotelId)
                ->where('account_code', '2-2110')
                ->exists();

            if ($exists) {
                continue;
            }

            $parentId = DB::table('chart_of_accounts')
                ->where('hotel_id', $hotelId)
                ->where('account_code', '2-0000')
                ->value('id');

            DB::table('chart_of_accounts')->insert([
                'hotel_id' => $hotelId,
                'parent_id' => $parentId,
                'account_code' => '2-2110',
                'name' => 'PBJT Terutang',
                'account_type' => AccountType::Liability->value,
                'normal_balance' => NormalBalance::Credit->value,
                'is_postable' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('chart_of_accounts')->where('account_code', '2-2110')->delete();
    }
};
