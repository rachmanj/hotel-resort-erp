<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folio_id')->constrained();
            $table->string('item_type');
            $table->string('description');
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('amount', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('service_charge_amount', 14, 2)->default(0);
            $table->string('original_currency_code', 3)->nullable();
            $table->decimal('original_amount', 14, 2)->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained();
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index(['folio_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folio_items');
    }
};
