<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->date('periode')->nullable()->after('bank_account_id');
            $table->decimal('statement_opening_balance', 18, 2)->nullable()->after('period_end_date');
            $table->decimal('statement_closing_balance', 18, 2)->nullable()->after('statement_opening_balance');
            $table->decimal('book_opening_balance', 18, 2)->nullable()->after('statement_closing_balance');
            $table->decimal('book_closing_balance', 18, 2)->nullable()->after('book_opening_balance');
            $table->string('statement_source', 160)->nullable()->after('book_balance');
            $table->string('statement_hash', 64)->nullable()->after('statement_source');
            $table->string('statement_format', 40)->nullable()->after('statement_hash');
            $table->string('source_mode', 16)->default('manual')->after('statement_format');
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->foreignId('validated_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('validated_by');
            $table->string('validation_status', 32)->nullable()->after('validated_at');
            $table->text('rejection_reason')->nullable()->after('validation_status');
            $table->timestamp('finalized_at')->nullable()->after('reconciled_at');
            $table->text('notes')->nullable()->after('finalized_at');
            $table->text('reopen_reason')->nullable()->after('notes');

            $table->unique(['bank_account_id', 'periode']);
        });

        DB::table('bank_reconciliations')
            ->whereNull('periode')
            ->update([
                'periode' => DB::raw('period_end_date'),
            ]);
    }

    public function down(): void
    {
        Schema::table('bank_reconciliations', function (Blueprint $table) {
            $table->dropUnique(['bank_account_id', 'periode']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['validated_by']);
            $table->dropColumn([
                'periode',
                'statement_opening_balance',
                'statement_closing_balance',
                'book_opening_balance',
                'book_closing_balance',
                'statement_source',
                'statement_hash',
                'statement_format',
                'source_mode',
                'created_by',
                'submitted_by',
                'submitted_at',
                'validated_by',
                'validated_at',
                'validation_status',
                'rejection_reason',
                'finalized_at',
                'notes',
                'reopen_reason',
            ]);
        });
    }
};
