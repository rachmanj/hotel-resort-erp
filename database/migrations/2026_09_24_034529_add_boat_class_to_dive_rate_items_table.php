<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dive_rate_items', function (Blueprint $table) {
            $table->string('boat_class', 30)->nullable()->after('boat_engine_option');
        });
    }

    public function down(): void
    {
        Schema::table('dive_rate_items', function (Blueprint $table) {
            $table->dropColumn('boat_class');
        });
    }
};
