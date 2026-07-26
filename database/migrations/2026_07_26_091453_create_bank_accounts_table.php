<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_no', 50);
            $table->string('account_name');
            $table->foreignId('chart_of_account_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3)->default('IDR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['hotel_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
