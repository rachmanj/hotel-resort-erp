<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_import_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revenue_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hotel_id')->nullable()->constrained()->nullOnDelete();
            $table->date('transaction_date');
            $table->string('invoice_no')->nullable();
            $table->string('guest_name')->nullable();
            $table->foreignId('revenue_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_code')->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index(['revenue_import_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_import_lines');
    }
};
