<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('general_ledger_id')->nullable()->constrained('general_ledger')->nullOnDelete();
            $table->string('statement_line_ref', 100)->nullable();
            $table->date('statement_date');
            $table->decimal('statement_amount', 14, 2);
            $table->boolean('is_matched')->default(false);
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->index('bank_reconciliation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_lines');
    }
};
