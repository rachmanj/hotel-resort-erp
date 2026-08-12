<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('promotion_id')->constrained()->nullOnDelete();
            $table->string('external_booking_id', 100)->nullable()->after('reservation_code');
            $table->index('agent_id');
            $table->unique(['hotel_id', 'external_booking_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('hotel_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_id');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique(['hotel_id', 'external_booking_id']);
            $table->dropConstrainedForeignId('agent_id');
            $table->dropColumn('external_booking_id');
        });
    }
};
