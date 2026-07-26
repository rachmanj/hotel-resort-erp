<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('general_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_period_id')->constrained()->cascadeOnDelete();
            $table->date('transaction_date');
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('description', 255);
            $table->string('reference_number', 50)->nullable();
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');
            $table->timestamps();

            $table->index(['hotel_id', 'chart_of_account_id', 'transaction_date'], 'gl_hotel_account_date_idx');
            $table->index(['source_type', 'source_id'], 'gl_source_idx');
            $table->index(['hotel_id', 'accounting_period_id'], 'gl_hotel_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('general_ledger');
    }
};
