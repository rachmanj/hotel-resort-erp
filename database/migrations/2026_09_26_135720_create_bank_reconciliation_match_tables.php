<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliation_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->string('match_type', 32);
            $table->float('confidence_score')->nullable();
            $table->decimal('bank_total', 18, 2)->default(0);
            $table->decimal('book_total', 18, 2)->default(0);
            $table->decimal('difference', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['bank_reconciliation_id', 'match_type'], 'br_matches_recon_type_idx');
        });

        Schema::create('bank_reconciliation_match_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_reconciliation_match_id');
            $table->unsignedBigInteger('bank_reconciliation_line_id');
            $table->foreign('bank_reconciliation_match_id', 'br_match_lines_match_fk')
                ->references('id')
                ->on('bank_reconciliation_matches')
                ->cascadeOnDelete();
            $table->foreign('bank_reconciliation_line_id', 'br_match_lines_statement_fk')
                ->references('id')
                ->on('bank_reconciliation_lines')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique('bank_reconciliation_line_id', 'br_match_lines_statement_line_unique');
            $table->unique(['bank_reconciliation_match_id', 'bank_reconciliation_line_id'], 'br_match_line_pair_unique');
        });

        Schema::create('bank_reconciliation_match_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_reconciliation_match_id');
            $table->unsignedBigInteger('bank_reconciliation_book_line_id');
            $table->foreign('bank_reconciliation_match_id', 'br_match_ledger_match_fk')
                ->references('id')
                ->on('bank_reconciliation_matches')
                ->cascadeOnDelete();
            $table->foreign('bank_reconciliation_book_line_id', 'br_match_ledger_book_fk')
                ->references('id')
                ->on('bank_reconciliation_book_lines')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique('bank_reconciliation_book_line_id', 'br_match_ledger_book_line_unique');
            $table->unique(['bank_reconciliation_match_id', 'bank_reconciliation_book_line_id'], 'br_match_ledger_pair_unique');
        });

        Schema::create('bank_reconciliation_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('bank_reconciliation_match_id')->nullable();
            $table->string('action', 32);
            $table->json('bank_line_ids')->nullable();
            $table->json('book_line_ids')->nullable();
            $table->json('amounts')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['bank_reconciliation_id', 'action'], 'br_audits_recon_action_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_audits');
        Schema::dropIfExists('bank_reconciliation_match_ledger');
        Schema::dropIfExists('bank_reconciliation_match_lines');
        Schema::dropIfExists('bank_reconciliation_matches');
    }
};
