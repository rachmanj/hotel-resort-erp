<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(1);
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->unsignedSmallInteger('nights')->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('service_charge_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->index(['guest_invoice_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_invoice_lines');
    }
};
