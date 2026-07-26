<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folio_id')->constrained();
            $table->decimal('amount', 14, 2);
            $table->string('method');
            $table->string('reference_no', 100)->nullable();
            $table->string('original_currency_code', 3)->nullable();
            $table->decimal('original_amount', 14, 2)->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained();
            $table->foreignId('received_by')->constrained('users');
            $table->timestamp('paid_at');
            $table->boolean('is_refund')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
