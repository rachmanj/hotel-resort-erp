<?php

namespace App\Services\Accounting\BankReconciliation\Statement;

readonly class ParsedBankStatement
{
    /**
     * @param  list<ParsedBankStatementLine>  $lines
     * @param  list<string>  $unparsedReasons
     */
    public function __construct(
        public string $profileCode,
        public string $accountNumber,
        public string $periodStart,
        public string $periodEnd,
        public float $openingBalance,
        public float $closingBalance,
        public float $totalDebit,
        public float $totalCredit,
        public int $debitCount,
        public int $creditCount,
        public array $lines,
        public int $unparsedRowCount,
        public array $unparsedReasons = [],
        public ?array $aiMeta = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'profile_code' => $this->profileCode,
            'account_number' => $this->accountNumber,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'opening_balance' => $this->openingBalance,
            'closing_balance' => $this->closingBalance,
            'total_debit' => $this->totalDebit,
            'total_credit' => $this->totalCredit,
            'debit_count' => $this->debitCount,
            'credit_count' => $this->creditCount,
            'lines' => array_map(static fn (ParsedBankStatementLine $line): array => $line->toArray(), $this->lines),
            'unparsed_rows' => [
                'count' => $this->unparsedRowCount,
                'reasons' => $this->unparsedReasons,
            ],
        ];
    }
}
