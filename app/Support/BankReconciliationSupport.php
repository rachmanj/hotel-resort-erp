<?php

namespace App\Support;

class BankReconciliationSupport
{
    public const TOLERANCE = 0.005;

    /**
     * Counter-account suggestions for bank-only statement lines (keyword → COA code from ChartOfAccountsSeeder).
     *
     * @var array<string, string>
     */
    public const COUNTER_ACCOUNT_BY_KEYWORD = [
        'biaya administrasi' => '6-8600',
        'bank charge' => '6-8600',
        'admin' => '6-8600',
        'adm' => '6-8600',
        'bunga' => '4-9000',
        'jasa giro' => '4-9000',
        'interest' => '4-9000',
        'pajak' => '2-2200',
        'pph' => '2-2200',
        'tax' => '2-2200',
    ];

    public static function lineHash(
        string $postingDate,
        string $direction,
        float $amount,
        ?string $reference,
        ?string $description,
        ?int $lineOrder = null,
    ): string {
        $parts = [
            $postingDate,
            $direction,
            number_format($amount, 2, '.', ''),
            trim((string) $reference),
            trim((string) $description),
        ];

        if ($lineOrder !== null) {
            $parts[] = (string) $lineOrder;
        }

        return hash('sha256', implode('|', $parts));
    }

    public static function amountsAreOpposite(
        float $bankDebit,
        float $bankCredit,
        float $bookDebit,
        float $bookCredit,
    ): bool {
        return abs($bankDebit - $bookCredit) <= self::TOLERANCE
            && abs($bankCredit - $bookDebit) <= self::TOLERANCE;
    }

    public static function suggestCounterAccountCode(string $description): ?string
    {
        $normalized = mb_strtolower(trim($description));

        if ($normalized === '') {
            return null;
        }

        $keywords = array_keys(self::COUNTER_ACCOUNT_BY_KEYWORD);
        usort($keywords, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($keywords as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return self::COUNTER_ACCOUNT_BY_KEYWORD[$keyword];
            }
        }

        return null;
    }

    public static function normalizeReference(?string $reference): string
    {
        if ($reference === null || $reference === '') {
            return '';
        }

        $upper = mb_strtoupper($reference);

        return preg_replace('/[^A-Z0-9]/', '', $upper) ?? '';
    }
}
