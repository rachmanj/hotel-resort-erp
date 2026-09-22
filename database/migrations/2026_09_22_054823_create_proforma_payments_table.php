<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained();
            $table->foreignId('proforma_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method', 20);
            $table->string('received_from', 150);
            $table->string('reference_no', 100)->nullable();
            $table->string('proof_path')->nullable();
            $table->date('paid_at');
            $table->string('status', 20)->default('recorded');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('receipt_sequence')->nullable();
            $table->string('receipt_number', 50)->nullable();
            $table->timestamp('receipt_issued_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('receipt_number');
            $table->index(['proforma_invoice_id', 'status']);
            $table->index(['reservation_id', 'paid_at']);
            $table->index(['hotel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_payments');
    }
};
