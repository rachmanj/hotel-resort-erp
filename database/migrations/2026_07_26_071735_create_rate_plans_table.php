<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('room_type_id')->constrained();
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('rate_type', 20);
            $table->decimal('nightly_rate', 14, 2);
            $table->tinyInteger('day_of_week_mask')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('room_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_plans');
    }
};
