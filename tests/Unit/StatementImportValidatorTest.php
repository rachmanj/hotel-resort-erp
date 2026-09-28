<?php

namespace Tests\Unit;

use App\Services\Accounting\BankReconciliation\Statement\ParsedBankStatement;
use App\Services\Accounting\BankReconciliation\Statement\ParsedBankStatementLine;
use App\Services\Accounting\BankReconciliation\Statement\StatementImportValidator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatementImportValidatorTest extends TestCase
{
    private StatementImportValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new StatementImportValidator;
    }

    #[Test]
    public function test_passes_consistent_statement(): void
    {
        $statement = $this->sampleStatement();

        $this->validator->validate($statement);

        $this->assertTrue(true);
    }

    #[Test]
    public function test_rejects_wrong_line_count(): void
    {
        $statement = $this->sampleStatement(debitCount: 2);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('line count mismatch');

        $this->validator->validate($statement);
    }

    #[Test]
    public function test_rejects_wrong_total_debit(): void
    {
        $statement = $this->sampleStatement(totalDebit: 999.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('total debit mismatch');

        $this->validator->validate($statement);
    }

    #[Test]
    public function test_rejects_wrong_total_credit(): void
    {
        $statement = $this->sampleStatement(totalCredit: 999.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('total credit mismatch');

        $this->validator->validate($statement);
    }

    #[Test]
    public function test_rejects_opening_balance_mismatch(): void
    {
        $statement = $this->sampleStatement(firstBalance: 500.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('opening balance mismatch');

        $this->validator->validate($statement);
    }

    #[Test]
    public function test_rejects_closing_balance_mismatch(): void
    {
        $statement = $this->sampleStatement(lastBalance: 500.0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('closing balance mismatch');

        $this->validator->validate($statement);
    }

    #[Test]
    public function test_rejects_broken_running_balance_chain(): void
    {
        $statement = new ParsedBankStatement(
            profileCode: 'test',
            accountNumber: '123',
            periodStart: '2026-07-01',
            periodEnd: '2026-07-31',
            openingBalance: 1000.0,
            closingBalance: 1200.0,
            totalDebit: 50.0,
            totalCredit: 250.0,
            debitCount: 1,
            creditCount: 2,
            lines: [
                new ParsedBankStatementLine('2026-07-01', 'Admin fee', null, 50.0, 0.0, 950.0, 0),
                new ParsedBankStatementLine('2026-07-02', 'Deposit A', null, 0.0, 150.0, 1100.0, 1),
                new ParsedBankStatementLine('2026-07-03', 'Deposit B', null, 0.0, 100.0, 999999.0, 2),
            ],
            unparsedRowCount: 0,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('running balance chain broke');

        $this->validator->validate($statement);
    }

    private function sampleStatement(
        int $debitCount = 1,
        int $creditCount = 1,
        float $totalDebit = 50.0,
        float $totalCredit = 200.0,
        ?float $firstBalance = null,
        ?float $lastBalance = null,
        ?float $secondBalance = null,
    ): ParsedBankStatement {
        $opening = 1000.0;
        $firstBalance ??= 950.0;
        $secondBalance ??= 1150.0;
        $lastBalance ??= 1150.0;

        return new ParsedBankStatement(
            profileCode: 'test',
            accountNumber: '123',
            periodStart: '2026-07-01',
            periodEnd: '2026-07-31',
            openingBalance: $opening,
            closingBalance: $lastBalance,
            totalDebit: $totalDebit,
            totalCredit: $totalCredit,
            debitCount: $debitCount,
            creditCount: $creditCount,
            lines: [
                new ParsedBankStatementLine('2026-07-01', 'Admin fee', null, 50.0, 0.0, $firstBalance, 0),
                new ParsedBankStatementLine('2026-07-02', 'Guest deposit', null, 0.0, 200.0, $secondBalance, 1),
            ],
            unparsedRowCount: 0,
        );
    }
}
