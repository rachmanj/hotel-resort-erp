<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_room_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 14, 2);
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->index(['reservation_id', 'promotion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_redemptions');
    }
};
