<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ota_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('fee_type', 20);
            $table->decimal('base_fee_pct', 5, 2)->nullable();
            $table->decimal('variable_fee_pct', 5, 2)->nullable();
            $table->decimal('flat_fee_per_room_night', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ota_fees');
    }
};
