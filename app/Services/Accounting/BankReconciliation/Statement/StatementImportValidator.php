<?php

namespace App\Services\Accounting\BankReconciliation\Statement;

use InvalidArgumentException;

class StatementImportValidator
{
    public function __construct(
        private float $tolerance = 0.01,
    ) {
        $this->tolerance = (float) config('bank_reconciliation.statement_import_tolerance', 0.01);
    }

    public function validate(ParsedBankStatement $statement): void
    {
        if ($statement->unparsedRowCount > 0) {
            throw new InvalidArgumentException(
                'Statement import failed: '.$statement->unparsedRowCount.' row(s) could not be parsed. '
                .($statement->unparsedReasons[0] ?? 'See unparsed rows for details.'),
            );
        }

        $parsedCount = count($statement->lines);
        $expectedCount = $statement->debitCount + $statement->creditCount;

        if ($parsedCount !== $expectedCount) {
            throw new InvalidArgumentException(
                "Statement import failed: line count mismatch. Parsed {$parsedCount} row(s) but the file summary shows {$expectedCount} ({$statement->debitCount} debit + {$statement->creditCount} credit).",
            );
        }

        $sumDebit = round(array_sum(array_map(static fn (ParsedBankStatementLine $line): float => $line->debit, $statement->lines)), 2);
        $sumCredit = round(array_sum(array_map(static fn (ParsedBankStatementLine $line): float => $line->credit, $statement->lines)), 2);

        if (abs($sumDebit - $statement->totalDebit) > $this->tolerance) {
            throw new InvalidArgumentException(
                'Statement import failed: total debit mismatch. Parsed sum is '
                .number_format($sumDebit, 2, '.', ',')
                .' but the file summary shows '
                .number_format($statement->totalDebit, 2, '.', ',').'.',
            );
        }

        if (abs($sumCredit - $statement->totalCredit) > $this->tolerance) {
            throw new InvalidArgumentException(
                'Statement import failed: total credit mismatch. Parsed sum is '
                .number_format($sumCredit, 2, '.', ',')
                .' but the file summary shows '
                .number_format($statement->totalCredit, 2, '.', ',').'.',
            );
        }

        if ($parsedCount === 0) {
            throw new InvalidArgumentException('Statement import failed: no transaction rows were parsed.');
        }

        $firstLine = $statement->lines[0];
        $firstBalance = $firstLine->balance;
        $expectedFirstBalance = round($statement->openingBalance + $firstLine->credit - $firstLine->debit, 2);

        if ($firstBalance !== null && abs($firstBalance - $expectedFirstBalance) > $this->tolerance) {
            throw new InvalidArgumentException(
                'Statement import failed: opening balance mismatch. After the first row the balance should be '
                .number_format($expectedFirstBalance, 2, '.', ',')
                .' (opening '
                .number_format($statement->openingBalance, 2, '.', ',')
                .' plus credit minus debit) but the row shows '
                .number_format($firstBalance, 2, '.', ',').'.',
            );
        }

        $allHaveBalance = true;
        foreach ($statement->lines as $line) {
            if ($line->balance === null) {
                $allHaveBalance = false;

                break;
            }
        }

        if ($allHaveBalance) {
            $running = $statement->openingBalance;

            foreach ($statement->lines as $index => $line) {
                $running = round($running + $line->credit - $line->debit, 2);

                if ($line->balance === null || abs($running - $line->balance) > $this->tolerance) {
                    throw new InvalidArgumentException(
                        'Statement import failed: running balance chain broke at row '.($index + 1)
                        .'. Expected balance '
                        .number_format($running, 2, '.', ',')
                        .' but the row shows '
                        .number_format((float) $line->balance, 2, '.', ',').'.',
                    );
                }
            }
        }

        $lastBalance = $statement->lines[$parsedCount - 1]->balance;
        if ($lastBalance !== null && abs($lastBalance - $statement->closingBalance) > $this->tolerance) {
            throw new InvalidArgumentException(
                'Statement import failed: closing balance mismatch. Last row balance is '
                .number_format($lastBalance, 2, '.', ',')
                .' but the file summary closing balance is '
                .number_format($statement->closingBalance, 2, '.', ',').'.',
            );
        }
    }
}
