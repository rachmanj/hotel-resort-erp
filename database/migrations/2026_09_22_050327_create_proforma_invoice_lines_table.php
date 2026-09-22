<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proforma_invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('description', 255);
            $table->string('note', 255)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('nights')->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['proforma_invoice_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_invoice_lines');
    }
};
