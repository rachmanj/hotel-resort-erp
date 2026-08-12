<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folios', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
        });

        Schema::table('folios', function (Blueprint $table) {
            $table->foreignId('reservation_id')->nullable()->change();
            $table->foreignId('reservation_group_id')
                ->nullable()
                ->after('reservation_id')
                ->constrained('reservation_groups')
                ->nullOnDelete();
        });

        Schema::table('folios', function (Blueprint $table) {
            $table->foreign('reservation_id')->references('id')->on('reservations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('folios', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->dropConstrainedForeignId('reservation_group_id');
        });

        Schema::table('folios', function (Blueprint $table) {
            $table->foreignId('reservation_id')->nullable(false)->change();
        });

        Schema::table('folios', function (Blueprint $table) {
            $table->foreign('reservation_id')->references('id')->on('reservations');
        });
    }
};
