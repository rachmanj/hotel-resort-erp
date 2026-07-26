<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ar_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_no', 20)->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('original_currency_code', 3)->nullable();
            $table->decimal('original_amount', 14, 2)->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->date('due_date');
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_invoices');
    }
};
