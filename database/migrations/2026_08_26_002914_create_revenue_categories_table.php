<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 150);
            $table->string('coa_account_code', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['hotel_id', 'code']);
            $table->index(['hotel_id', 'is_active']);
            $table->index(['hotel_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_categories');
    }
};
