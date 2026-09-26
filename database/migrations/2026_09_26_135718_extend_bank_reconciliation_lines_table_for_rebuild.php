<?php

use App\Support\BankReconciliationSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_reconciliation_lines', function (Blueprint $table) {
            $table->date('posting_date')->nullable()->after('bank_reconciliation_id');
            $table->date('value_date')->nullable()->after('posting_date');
            $table->text('description')->nullable()->after('statement_line_ref');
            $table->string('reference', 191)->nullable()->after('description');
            $table->decimal('debit', 18, 2)->default(0)->after('statement_amount');
            $table->decimal('credit', 18, 2)->default(0)->after('debit');
            $table->decimal('amount', 18, 2)->default(0)->after('credit');
            $table->string('direction', 10)->nullable()->after('amount');
            $table->decimal('running_balance', 18, 2)->nullable()->after('direction');
            $table->string('match_status', 20)->default('unmatched')->after('is_matched');
            $table->string('exclude_reason', 500)->nullable()->after('match_status');
            $table->string('line_notes', 500)->nullable()->after('exclude_reason');
            $table->unsignedInteger('line_order')->nullable()->after('line_notes');
            $table->string('line_hash', 64)->nullable()->after('line_order');
            $table->boolean('is_ai_extracted')->default(false)->after('line_hash');
            $table->json('ai_meta')->nullable()->after('is_ai_extracted');
            $table->foreignId('adjusting_journal_id')->nullable()->after('ai_meta')->constrained('journal_entries')->nullOnDelete();
            $table->boolean('is_carried_forward')->default(false)->after('matched_at');
            $table->foreignId('carried_from_line_id')->nullable()->after('is_carried_forward')->constrained('bank_reconciliation_lines')->nullOnDelete();
            $table->unsignedBigInteger('origin_reconciliation_id')->nullable()->after('carried_from_line_id');

            $table->index(['bank_reconciliation_id', 'match_status'], 'br_lines_recon_match_status_idx');
        });

        DB::table('bank_reconciliation_lines')->orderBy('id')->chunkById(200, function ($lines): void {
            foreach ($lines as $line) {
                $statementAmount = (float) $line->statement_amount;
                $absAmount = abs($statementAmount);
                $debit = 0.0;
                $credit = 0.0;
                $direction = 'credit';

                if ($statementAmount > 0) {
                    $credit = $absAmount;
                    $direction = 'credit';
                } elseif ($statementAmount < 0) {
                    $debit = $absAmount;
                    $direction = 'debit';
                }

                $postingDate = $line->statement_date;
                $reference = $line->statement_line_ref;

                DB::table('bank_reconciliation_lines')
                    ->where('id', $line->id)
                    ->update([
                        'posting_date' => $postingDate,
                        'debit' => $debit,
                        'credit' => $credit,
                        'amount' => $absAmount,
                        'direction' => $direction,
                        'match_status' => $line->is_matched ? 'manual' : 'unmatched',
                        'line_hash' => BankReconciliationSupport::lineHash(
                            (string) $postingDate,
                            $direction,
                            $absAmount,
                            $reference,
                            null,
                        ),
                    ]);
            }
        });

        Schema::table('bank_reconciliation_lines', function (Blueprint $table) {
            $table->unique(['bank_reconciliation_id', 'line_hash'], 'br_lines_recon_line_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bank_reconciliation_lines', function (Blueprint $table) {
            $table->dropUnique('br_lines_recon_line_hash_unique');
            $table->dropIndex('br_lines_recon_match_status_idx');
            $table->dropForeign(['adjusting_journal_id']);
            $table->dropForeign(['carried_from_line_id']);
            $table->dropColumn([
                'posting_date',
                'value_date',
                'description',
                'reference',
                'debit',
                'credit',
                'amount',
                'direction',
                'running_balance',
                'match_status',
                'exclude_reason',
                'line_notes',
                'line_order',
                'line_hash',
                'is_ai_extracted',
                'ai_meta',
                'adjusting_journal_id',
                'is_carried_forward',
                'carried_from_line_id',
                'origin_reconciliation_id',
            ]);
        });
    }
};
