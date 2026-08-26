<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ota_fee_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ota_fee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_amount', 12, 2);
            $table->decimal('fee_pct', 5, 2)->nullable();
            $table->decimal('fee_amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->timestamp('earned_at')->nullable();
            $table->timestamps();

            $table->unique('reservation_id');
            $table->index(['ota_fee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ota_fee_charges');
    }
};
