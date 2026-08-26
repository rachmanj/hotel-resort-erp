<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->foreignId('revenue_category_id')
                ->nullable()
                ->after('hotel_id')
                ->constrained('revenue_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revenue_category_id');
        });
    }
};
