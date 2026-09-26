<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliation_book_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('general_ledger_id')->nullable();
            $table->date('posting_date')->nullable();
            $table->string('doc_num', 191)->nullable();
            $table->string('reference_number', 191)->nullable();
            $table->text('description')->nullable();
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('match_status', 20)->default('unmatched');
            $table->string('exclude_reason', 500)->nullable();
            $table->string('line_notes', 500)->nullable();
            $table->boolean('is_carried_forward')->default(false);
            $table->foreignId('carried_from_book_line_id')->nullable()->constrained('bank_reconciliation_book_lines')->nullOnDelete();
            $table->unsignedBigInteger('origin_reconciliation_id')->nullable();
            $table->boolean('is_stale')->default(false);
            $table->string('stale_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['bank_reconciliation_id', 'general_ledger_id'], 'br_book_gl_unique');
            $table->index(['bank_reconciliation_id', 'match_status'], 'br_book_recon_match_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_book_lines');
    }
};
