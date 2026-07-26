<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_id')->constrained();
            $table->foreignId('reservation_id')->nullable()->constrained();
            $table->foreignId('room_id')->nullable()->constrained();
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->smallInteger('nights')->default(0);
            $table->decimal('total_spend', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_stays');
    }
};
