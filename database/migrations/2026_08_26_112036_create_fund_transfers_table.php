<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('transfer_no', 50);
            $table->foreignId('from_chart_of_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->foreignId('to_chart_of_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
            $table->foreignId('from_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('to_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('transfer_date');
            $table->string('description');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['hotel_id', 'transfer_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_transfers');
    }
};
