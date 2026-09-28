<?php

namespace App\Actions\Accounting;

use App\Enums\BankStatementLineStatus;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationAudit;
use App\Models\BankReconciliationLine;
use App\Models\User;
use App\Services\Accounting\BankReconciliation\Statement\BankStatementAiReader;
use App\Services\Accounting\BankReconciliation\Statement\BankStatementProfileParserRegistry;
use App\Services\Accounting\BankReconciliation\Statement\BankStatementTextReader;
use App\Services\Accounting\BankReconciliation\Statement\ParsedBankStatement;
use App\Services\Accounting\BankReconciliation\Statement\ParsedBankStatementLine;
use App\Services\Accounting\BankReconciliation\Statement\StatementImportValidator;
use App\Support\BankReconciliationSupport;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ImportBankStatementAction
{
    public function __construct(
        private BankStatementTextReader $textReader,
        private BankStatementProfileParserRegistry $parserRegistry,
        private StatementImportValidator $validator,
        private BankStatementAiReader $aiReader,
    ) {}

    public function __invoke(
        BankReconciliation $reconciliation,
        string $absolutePath,
        string $originalFilename,
        ?string $profileCode = null,
        bool $dryRun = false,
        ?User $actor = null,
        bool $replace = false,
    ): ImportBankStatementResult {
        if ($reconciliation->isLockedForEditing()) {
            throw new InvalidArgumentException('This bank reconciliation is locked for editing.');
        }

        $this->assertImportAllowed($reconciliation, $replace);

        $statementHash = hash_file('sha256', $absolutePath) ?: '';
        $text = $this->textReader->read($absolutePath);

        [$statement, $validationMessage] = $this->parseAndValidate($text, $profileCode);

        $existingHashes = $reconciliation->lines()
            ->where('is_carried_forward', false)
            ->pluck('line_hash')
            ->all();

        $newHashes = array_map(
            fn (ParsedBankStatementLine $line): string => $this->lineHashFor($line),
            $statement->lines,
        );

        $newOnly = array_diff($newHashes, $existingHashes);

        if ($statementHash === $reconciliation->statement_hash && $newOnly === []) {
            throw new InvalidArgumentException('This statement file was already imported and contains no new rows.');
        }

        if ($dryRun) {
            return new ImportBankStatementResult(
                dryRun: true,
                validationPassed: true,
                validationMessage: null,
                statement: $statement,
                linesWritten: count($newOnly),
                linesSkipped: count($statement->lines) - count($newOnly),
                statementHash: $statementHash,
                profileCode: $statement->profileCode,
            );
        }

        $linesWritten = 0;

        DB::transaction(function () use ($reconciliation, $statement, $statementHash, $originalFilename, $actor, &$linesWritten): void {
            $reconciliation->lines()
                ->where('is_carried_forward', false)
                ->delete();

            foreach ($statement->lines as $line) {
                $hash = $this->lineHashFor($line);

                $direction = $line->debit > 0.0 ? 'debit' : 'credit';
                $amount = round(max($line->debit, $line->credit), 2);
                $signedAmount = $direction === 'debit' ? -$amount : $amount;

                BankReconciliationLine::query()->create([
                    'bank_reconciliation_id' => $reconciliation->id,
                    'posting_date' => $line->postingDate,
                    'statement_date' => $line->postingDate,
                    'description' => $line->description,
                    'reference' => $line->reference,
                    'statement_line_ref' => $line->reference,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'amount' => $amount,
                    'direction' => $direction,
                    'statement_amount' => $signedAmount,
                    'running_balance' => $line->balance,
                    'line_order' => $line->lineOrder,
                    'line_hash' => $hash,
                    'match_status' => BankStatementLineStatus::Unmatched,
                    'is_ai_extracted' => $statement->profileCode === 'pdf_ai',
                    'ai_meta' => $statement->aiMeta,
                ]);

                $linesWritten++;
            }

            $reconciliation->update([
                'statement_source' => $originalFilename,
                'statement_hash' => $statementHash,
                'statement_format' => $statement->profileCode,
                'source_mode' => $statement->profileCode === 'pdf_ai' ? 'ai' : 'file',
                'statement_opening_balance' => $statement->openingBalance,
                'statement_closing_balance' => $statement->closingBalance,
                'statement_balance' => $statement->closingBalance,
            ]);

            BankReconciliationAudit::query()->create([
                'bank_reconciliation_id' => $reconciliation->id,
                'action' => 'imported',
                'amounts' => [
                    'profile_code' => $statement->profileCode,
                    'line_count' => count($statement->lines),
                    'debit_count' => $statement->debitCount,
                    'credit_count' => $statement->creditCount,
                    'total_debit' => $statement->totalDebit,
                    'total_credit' => $statement->totalCredit,
                    'opening_balance' => $statement->openingBalance,
                    'closing_balance' => $statement->closingBalance,
                    'lines_written' => $linesWritten,
                    'ai_meta' => $statement->aiMeta,
                ],
                'performed_by' => $actor?->id,
                'notes' => $originalFilename,
            ]);
        });

        return new ImportBankStatementResult(
            dryRun: false,
            validationPassed: true,
            validationMessage: $validationMessage,
            statement: $statement,
            linesWritten: $linesWritten,
            linesSkipped: count($statement->lines) - $linesWritten,
            statementHash: $statementHash,
            profileCode: $statement->profileCode,
        );
    }

    /**
     * @return array{0: ParsedBankStatement, 1: ?string}
     */
    private function parseAndValidate(string $text, ?string $profileCode): array
    {
        $parser = $profileCode !== null
            ? $this->parserRegistry->get($profileCode)
            : $this->parserRegistry->detect($text);

        if ($parser === null) {
            throw new InvalidArgumentException('Could not detect a supported bank statement profile for this file.');
        }

        try {
            $statement = $parser->parse($text);
            $this->validator->validate($statement);

            return [$statement, null];
        } catch (InvalidArgumentException $firstFailure) {
            if (! $this->aiReader->isEnabled()) {
                throw new InvalidArgumentException(
                    $firstFailure->getMessage().' AI fallback is disabled because BANK_RECON_AI_KEY is not configured.',
                );
            }

            $statement = $this->aiReader->parse($text, $parser->code());
            $this->validator->validate($statement);

            return [$statement, $firstFailure->getMessage()];
        }
    }

    private function assertImportAllowed(BankReconciliation $reconciliation, bool $replace): void
    {
        if ($replace) {
            return;
        }

        $blocking = $reconciliation->lines()
            ->where('is_carried_forward', false)
            ->where(function ($query): void {
                $query->where('match_status', '!=', BankStatementLineStatus::Unmatched->value)
                    ->orWhereNotNull('adjusting_journal_id');
            })
            ->exists();

        if ($blocking) {
            throw new InvalidArgumentException(
                'This reconciliation already has matched, excluded, outstanding, or adjusted statement lines. '
                .'Use replace mode to import a new file.',
            );
        }
    }

    private function lineHashFor(ParsedBankStatementLine $line): string
    {
        $direction = $line->debit > 0.0 ? 'debit' : 'credit';
        $amount = round(max($line->debit, $line->credit), 2);

        return BankReconciliationSupport::lineHash(
            $line->postingDate,
            $direction,
            $amount,
            $line->reference,
            $line->description,
            $line->lineOrder,
        );
    }
}
