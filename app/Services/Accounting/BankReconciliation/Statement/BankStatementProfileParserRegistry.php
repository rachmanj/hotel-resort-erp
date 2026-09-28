<?php

namespace App\Services\Accounting\BankReconciliation\Statement;

use InvalidArgumentException;

class BankStatementProfileParserRegistry
{
    /**
     * @param  list<BankStatementProfileParser>  $parsers
     */
    public function __construct(
        private array $parsers,
    ) {}

    public function detect(string $text): ?BankStatementProfileParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($text)) {
                return $parser;
            }
        }

        return null;
    }

    public function get(string $profileCode): BankStatementProfileParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->code() === $profileCode) {
                return $parser;
            }
        }

        throw new InvalidArgumentException("Unknown bank statement profile: {$profileCode}.");
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public function options(): array
    {
        return [
            ['code' => 'bca_giro_pdf', 'label' => 'BCA Giro (PDF)'],
            ['code' => 'mandiri_kopra_pdf', 'label' => 'Mandiri Kopra (PDF)'],
        ];
    }
}
