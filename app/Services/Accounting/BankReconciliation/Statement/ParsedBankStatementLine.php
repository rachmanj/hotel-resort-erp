<?php

namespace App\Services\Accounting\BankReconciliation\Statement;

readonly class ParsedBankStatementLine
{
    public function __construct(
        public string $postingDate,
        public string $description,
        public ?string $reference,
        public float $debit,
        public float $credit,
        public ?float $balance,
        public int $lineOrder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'posting_date' => $this->postingDate,
            'description' => $this->description,
            'reference' => $this->reference,
            'debit' => $this->debit,
            'credit' => $this->credit,
            'balance' => $this->balance,
            'line_order' => $this->lineOrder,
        ];
    }
}
