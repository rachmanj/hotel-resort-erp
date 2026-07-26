<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained();
            $table->string('folio_no', 20)->unique();
            $table->foreignId('reservation_id')->constrained();
            $table->foreignId('guest_id')->constrained();
            $table->foreignId('company_id')->nullable()->constrained();
            $table->string('type')->default('master');
            $table->string('status')->default('open');
            $table->string('display_currency_code', 3)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folios');
    }
};
