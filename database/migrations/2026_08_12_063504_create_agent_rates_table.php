<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained();
            $table->foreignId('rate_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('nightly_rate', 14, 2)->nullable();
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_value', 14, 2)->nullable();
            $table->date('valid_from');
            $table->date('valid_to');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['agent_id', 'room_type_id', 'valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_rates');
    }
};
