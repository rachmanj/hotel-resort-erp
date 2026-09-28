<?php

namespace App\Services\Accounting\BankReconciliation\Statement;

interface BankStatementProfileParser
{
    public function code(): string;

    public function supports(string $text): bool;

    public function parse(string $text): ParsedBankStatement;
}
