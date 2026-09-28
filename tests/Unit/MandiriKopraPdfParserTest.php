<?php

namespace Tests\Unit;

use App\Services\Accounting\BankReconciliation\Statement\MandiriKopraPdfParser;
use App\Services\Accounting\BankReconciliation\Statement\StatementImportValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MandiriKopraPdfParserTest extends TestCase
{
    private MandiriKopraPdfParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new MandiriKopraPdfParser;
    }

    #[Test]
    public function test_parses_sample_statement_with_dash_debit_column(): void
    {
        $text = file_get_contents(base_path('tests/Fixtures/bank-statements/mandiri_kopra_sample.txt'));

        $statement = $this->parser->parse($text);

        $this->assertSame('1234567890123', $statement->accountNumber);
        $this->assertCount(2, $statement->lines);
        $this->assertSame(200000.0, $statement->lines[0]->credit);
        $this->assertSame(50000.0, $statement->lines[1]->debit);

        (new StatementImportValidator)->validate($statement);
    }

    #[Test]
    public function test_supports_mandiri_letterhead(): void
    {
        $text = "Account Statement\nAccount No.\n";

        $this->assertTrue($this->parser->supports($text));
    }
}
