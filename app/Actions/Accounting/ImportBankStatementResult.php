<?php

namespace App\Actions\Accounting;

use App\Services\Accounting\BankReconciliation\Statement\ParsedBankStatement;

readonly class ImportBankStatementResult
{
    public function __construct(
        public bool $dryRun,
        public bool $validationPassed,
        public ?string $validationMessage,
        public ParsedBankStatement $statement,
        public int $linesWritten,
        public int $linesSkipped,
        public string $statementHash,
        public ?string $profileCode,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'dry_run' => $this->dryRun,
            'validation_passed' => $this->validationPassed,
            'validation_message' => $this->validationMessage,
            'statement' => $this->statement->toArray(),
            'lines_written' => $this->linesWritten,
            'lines_skipped' => $this->linesSkipped,
            'statement_hash' => $this->statementHash,
            'profile_code' => $this->profileCode,
        ];
    }
}
