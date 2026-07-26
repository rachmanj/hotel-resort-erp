<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('tax_type', 20);
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');
            $table->date('transaction_date');
            $table->decimal('base_amount', 14, 2);
            $table->decimal('tax_rate_percent', 5, 2);
            $table->decimal('tax_amount', 14, 2);
            $table->string('tax_period', 7);
            $table->string('status', 20)->default('unreported');
            $table->timestamps();

            $table->index(['tax_type', 'tax_period']);
            $table->index(['source_type', 'source_id']);
            $table->index(['hotel_id', 'tax_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_transactions');
    }
};
