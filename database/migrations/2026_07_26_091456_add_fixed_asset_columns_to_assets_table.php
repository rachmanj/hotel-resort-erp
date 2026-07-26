<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('asset_code', 30)->nullable()->after('name');
            $table->date('acquisition_date')->nullable()->after('purchased_at');
            $table->decimal('acquisition_cost', 14, 2)->nullable()->after('acquisition_date');
            $table->decimal('residual_value', 14, 2)->default(0)->after('acquisition_cost');
            $table->unsignedSmallInteger('useful_life_years')->nullable()->after('residual_value');
            $table->string('depreciation_method', 30)->nullable()->after('useful_life_years');
            $table->decimal('accumulated_depreciation', 14, 2)->default(0)->after('depreciation_method');
            $table->decimal('net_book_value', 14, 2)->nullable()->after('accumulated_depreciation');
            $table->date('last_depreciation_date')->nullable()->after('net_book_value');
            $table->foreignId('chart_of_account_id')->nullable()->after('last_depreciation_date')->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('accumulated_depreciation_account_id')->nullable()->after('chart_of_account_id')->constrained('chart_of_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chart_of_account_id');
            $table->dropConstrainedForeignId('accumulated_depreciation_account_id');
            $table->dropColumn([
                'asset_code',
                'acquisition_date',
                'acquisition_cost',
                'residual_value',
                'useful_life_years',
                'depreciation_method',
                'accumulated_depreciation',
                'net_book_value',
                'last_depreciation_date',
            ]);
        });
    }
};
