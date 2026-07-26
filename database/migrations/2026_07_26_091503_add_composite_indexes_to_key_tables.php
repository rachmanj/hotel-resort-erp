<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->index(['hotel_id', 'status'], 'reservations_hotel_status_idx');
        });

        Schema::table('folios', function (Blueprint $table) {
            $table->index(['hotel_id', 'status'], 'folios_hotel_status_idx');
        });

        Schema::table('general_ledger', function (Blueprint $table) {
            $table->index(['hotel_id', 'transaction_date'], 'gl_hotel_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_hotel_status_idx');
        });

        Schema::table('folios', function (Blueprint $table) {
            $table->dropIndex('folios_hotel_status_idx');
        });

        Schema::table('general_ledger', function (Blueprint $table) {
            $table->dropIndex('gl_hotel_date_idx');
        });
    }
};
