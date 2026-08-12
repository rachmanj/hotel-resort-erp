<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('promo_type', 20);
            $table->string('discount_type', 20);
            $table->decimal('discount_value', 14, 2);
            $table->foreignId('rate_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('lead_time_min_days')->nullable();
            $table->integer('lead_time_max_days')->nullable();
            $table->integer('min_nights')->nullable();
            $table->integer('max_nights')->nullable();
            $table->date('valid_from');
            $table->date('valid_to');
            $table->boolean('is_stackable')->default(false);
            $table->boolean('requires_code')->default(false);
            $table->integer('max_uses')->nullable();
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['hotel_id', 'is_active']);
            $table->index(['valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
