<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('guest_id')->constrained()->nullOnDelete();
        });

        Schema::table('reservation_rooms', function (Blueprint $table) {
            $table->foreignId('promotion_id')->nullable()->after('rate_plan_id')->constrained()->nullOnDelete();
            $table->decimal('gross_nightly_rate', 14, 2)->nullable()->after('nightly_rate');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn('gross_nightly_rate');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
        });
    }
};
