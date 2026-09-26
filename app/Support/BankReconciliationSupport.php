<?php

namespace App\Support;

class BankReconciliationSupport
{
    public const TOLERANCE = 0.005;

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
}
