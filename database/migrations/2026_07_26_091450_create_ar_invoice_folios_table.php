<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ar_invoice_folios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ar_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ar_invoice_id', 'folio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_invoice_folios');
    }
};
