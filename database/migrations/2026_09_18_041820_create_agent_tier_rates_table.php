<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_tier_rates', function (Blueprint $table) {
            $table->id();
            $table->string('rate_category', 1);
            $table->foreignId('room_type_id')->constrained();
            $table->decimal('nightly_rate', 14, 2);
            $table->date('valid_from');
            $table->date('valid_to');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['rate_category', 'room_type_id', 'valid_from', 'valid_to'], 'agent_tier_rates_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_tier_rates');
    }
};
