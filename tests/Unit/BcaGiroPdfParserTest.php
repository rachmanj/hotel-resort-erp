<?php

namespace Tests\Unit;

use App\Services\Accounting\BankReconciliation\Statement\BcaGiroPdfParser;
use App\Services\Accounting\BankReconciliation\Statement\StatementImportValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BcaGiroPdfParserTest extends TestCase
{
    private BcaGiroPdfParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new BcaGiroPdfParser;
    }

    #[Test]
    public function test_parses_sample_statement_and_skips_saldo_awal_row(): void
    {
        $text = file_get_contents(base_path('tests/Fixtures/bank-statements/bca_giro_sample.txt'));

        $statement = $this->parser->parse($text);

        $this->assertSame('1234567890', $statement->accountNumber);
        $this->assertCount(3, $statement->lines);
        $this->assertStringNotContainsString('SALDO AWAL', $statement->lines[0]->description);
        $this->assertSame(30000.0, $statement->lines[0]->debit);
        $this->assertSame(0.0, $statement->lines[0]->credit);

        (new StatementImportValidator)->validate($statement);
    }

    #[Test]
    public function test_db_suffix_row_is_debit(): void
    {
        $text = file_get_contents(base_path('tests/Fixtures/bank-statements/bca_giro_sample.txt'));
        $statement = $this->parser->parse($text);

        $this->assertGreaterThan(0, $statement->lines[0]->debit);
        $this->assertSame(0.0, $statement->lines[0]->credit);
    }

    #[Test]
    public function test_reports_unparsed_row_when_amount_missing(): void
    {
        $text = file_get_contents(base_path('tests/Fixtures/bank-statements/bca_giro_unparsed.txt'));
        $statement = $this->parser->parse($text);

        $this->assertGreaterThan(0, $statement->unparsedRowCount);
    }
}
