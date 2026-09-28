<?php

namespace App\Services\Accounting\BankReconciliation\Statement;

use Carbon\Carbon;

class BcaGiroPdfParser implements BankStatementProfileParser
{
    /** @var array<string, int> */
    private const INDONESIAN_MONTHS = [
        'JANUARI' => 1,
        'FEBRUARI' => 2,
        'MARET' => 3,
        'APRIL' => 4,
        'MEI' => 5,
        'JUNI' => 6,
        'JULI' => 7,
        'AGUSTUS' => 8,
        'SEPTEMBER' => 9,
        'OKTOBER' => 10,
        'NOVEMBER' => 11,
        'DESEMBER' => 12,
    ];

    public function code(): string
    {
        return 'bca_giro_pdf';
    }

    public function supports(string $text): bool
    {
        return str_contains($text, 'REKENING GIRO')
            && (str_contains($text, 'NO. REKENING') || str_contains($text, 'NO. REKENING :'));
    }

    public function parse(string $text): ParsedBankStatement
    {
        $accountNumber = $this->extractAccountNumber($text);
        [$periodStart, $periodEnd, $year] = $this->extractPeriod($text);
        [$opening, $closing, $totalDebit, $totalCredit, $debitCount, $creditCount] = $this->extractSummary($text);

        [$lines, $unparsedReasons] = $this->extractLines($text, $year, $opening);

        return new ParsedBankStatement(
            profileCode: $this->code(),
            accountNumber: $accountNumber,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            openingBalance: $opening,
            closingBalance: $closing,
            totalDebit: $totalDebit,
            totalCredit: $totalCredit,
            debitCount: $debitCount,
            creditCount: $creditCount,
            lines: $lines,
            unparsedRowCount: count($unparsedReasons),
            unparsedReasons: $unparsedReasons,
        );
    }

    private function extractAccountNumber(string $text): string
    {
        if (preg_match('/NO\.\s*REKENING\s*:\s*(\d+)/', $text, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function extractPeriod(string $text): array
    {
        if (! preg_match('/PERIODE\s*:\s*([A-Z]+)\s+(\d{4})/', $text, $matches)) {
            $now = now();

            return [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString(), (int) $now->year];
        }

        $monthName = mb_strtoupper(trim($matches[1]));
        $year = (int) $matches[2];
        $month = self::INDONESIAN_MONTHS[$monthName] ?? 1;
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        return [$start->toDateString(), $end->toDateString(), $year];
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float, 4: int, 5: int}
     */
    private function extractSummary(string $text): array
    {
        $opening = $this->matchMoney($text, '/SALDO\s+AWAL\s*:\s*([\d,]+\.\d{2})/');
        $closing = $this->matchMoney($text, '/SALDO\s+AKHIR\s*:\s*([\d,]+\.\d{2})/');
        $totalCredit = $this->matchMoney($text, '/MUTASI\s+CR\s*:\s*([\d,]+\.\d{2})/');
        $totalDebit = $this->matchMoney($text, '/MUTASI\s+DB\s*:\s*([\d,]+\.\d{2})/');
        $creditCount = 0;
        $debitCount = 0;

        if (preg_match('/MUTASI\s+CR\s*:\s*[\d,]+\.\d{2}\s+(\d+)/', $text, $matches)) {
            $creditCount = (int) $matches[1];
        }

        if (preg_match('/MUTASI\s+DB\s*:\s*[\d,]+\.\d{2}\s+(\d+)/', $text, $matches)) {
            $debitCount = (int) $matches[1];
        }

        return [$opening, $closing, $totalDebit, $totalCredit, $debitCount, $creditCount];
    }

    /**
     * @return array{0: list<ParsedBankStatementLine>, 1: list<string>}
     */
    private function extractLines(string $text, int $year, float $opening): array
    {
        $lines = [];
        $unparsed = [];
        $lineOrder = 0;

        $rawLines = preg_split('/\R/', $text) ?: [];
        $blocks = [];
        $currentDate = null;
        $currentParts = [];

        foreach ($rawLines as $rawLine) {
            $line = $this->normalizeLine($rawLine);

            if ($line === '' || $this->isNoiseLine($line) || preg_match('/^SALDO\s+AWAL\b/i', $line)) {
                continue;
            }

            if (preg_match('/^(\d{2})\/(\d{2})\s+(.+)$/', $line, $dateMatch)) {
                if ($currentDate !== null) {
                    $blocks[] = ['date' => $currentDate, 'parts' => $currentParts];
                }

                $currentDate = sprintf('%02d/%02d', (int) $dateMatch[1], (int) $dateMatch[2]);
                $currentParts = [trim($dateMatch[3])];

                continue;
            }

            if ($currentDate !== null) {
                $currentParts[] = $line;
            }
        }

        if ($currentDate !== null) {
            $blocks[] = ['date' => $currentDate, 'parts' => $currentParts];
        }

        foreach ($blocks as $block) {
            if ($this->isSaldoAwalBlock($block['parts'])) {
                continue;
            }

            $parsed = $this->parseBlock($block['date'], $block['parts'], $year, $lineOrder);
            if ($parsed === null) {
                $unparsed[] = 'Could not parse transaction on '.$block['date'].': '.mb_substr(implode(' ', $block['parts']), 0, 120);

                continue;
            }

            $lines[] = $parsed;
            $lineOrder++;
        }

        $lines = $this->fillMissingBalances($lines, $opening);

        return [$lines, $unparsed];
    }

    /**
     * @param  list<string>  $parts
     */
    /**
     * @param  list<string>  $parts
     */
    private function isSaldoAwalBlock(array $parts): bool
    {
        $head = mb_strtoupper(trim($parts[0] ?? ''));

        return str_starts_with($head, 'SALDO AWAL');
    }

    /**
     * @param  list<string>  $parts
     */
    private function parseBlock(string $ddmm, array $parts, int $year, int $lineOrder): ?ParsedBankStatementLine
    {
        if ($parts === []) {
            return null;
        }

        $first = $parts[0];
        $inline = $this->parseAmountTail($first);
        if ($inline !== null && count($parts) === 1) {
            [$desc, $debit, $credit, $balance] = $inline;

            return $this->makeLine($ddmm, [$desc], $debit, $credit, $balance, $year, $lineOrder);
        }

        for ($index = count($parts) - 1; $index >= 0; $index--) {
            $amountParse = $this->parseAmountLine($parts[$index]);
            if ($amountParse === null) {
                continue;
            }

            [$debit, $credit, $balance] = $amountParse;
            $descParts = $parts;
            array_splice($descParts, $index, 1);

            if ($inline !== null) {
                [$desc, $inlineDebit, $inlineCredit, $inlineBalance] = $inline;
                array_unshift($descParts, $desc);
                if ($index === count($parts) - 1 && $debit === 0.0 && $credit === 0.0) {
                    $debit = $inlineDebit;
                    $credit = $inlineCredit;
                    $balance = $inlineBalance ?? $balance;
                }
            }

            return $this->makeLine($ddmm, $descParts, $debit, $credit, $balance, $year, $lineOrder);
        }

        if ($inline !== null) {
            [$desc, $debit, $credit, $balance] = $inline;

            return $this->makeLine($ddmm, [$desc, ...array_slice($parts, 1)], $debit, $credit, $balance, $year, $lineOrder);
        }

        return null;
    }

    /**
     * @param  list<ParsedBankStatementLine>  $lines
     * @return list<ParsedBankStatementLine>
     */
    private function fillMissingBalances(array $lines, float $openingBalance): array
    {
        $running = $openingBalance;
        $filled = [];

        foreach ($lines as $line) {
            $running = round($running + $line->credit - $line->debit, 2);
            $balance = $line->balance ?? $running;

            $filled[] = new ParsedBankStatementLine(
                postingDate: $line->postingDate,
                description: $line->description,
                reference: $line->reference,
                debit: $line->debit,
                credit: $line->credit,
                balance: $balance,
                lineOrder: $line->lineOrder,
            );
        }

        return $filled;
    }

    private function normalizeLine(string $rawLine): string
    {
        $line = str_replace(["\t", "\x0c"], ' ', $rawLine);
        $line = preg_replace('/\s+DB\b/u', ' DB', $line) ?? $line;
        $line = preg_replace('/\s+/', ' ', trim($line)) ?? trim($line);

        return $line;
    }

    private function isNoiseLine(string $line): bool
    {
        if (preg_match('/^REKENING GIRO|^KCP |^PRATASABA|^BPP UTARA|^GRAHA INDAH|^JL MT HARYONO|^BALIKPAPAN|^INDONESIA$|^NO\.\s*REKENING|^HALAMAN|^PERIODE|^MATA UANG|^CATATAN:|^Apabila nasabah|^telah menyetujui|^•|^\d+\s*\/\s*\d+$|^TANGGAL KETERANGAN|^Bersambung ke halaman|^MUTASI CR|^MUTASI DB|^SALDO AKHIR/i', $line)) {
            return true;
        }

        return preg_match('/^BCA berhak/', $line) === 1;
    }

    /**
     * @param  list<string>  $parts
     */
    private function makeLine(
        string $ddmm,
        array $parts,
        float $debit,
        float $credit,
        ?float $balance,
        int $year,
        int $lineOrder,
    ): ParsedBankStatementLine {
        $description = $this->buildDescription($parts);
        $reference = $this->extractReference($description);
        $postingDate = $this->toPostingDate($ddmm, $year);

        return new ParsedBankStatementLine(
            postingDate: $postingDate,
            description: $description,
            reference: $reference,
            debit: $debit,
            credit: $credit,
            balance: $balance,
            lineOrder: $lineOrder,
        );
    }

    /**
     * @param  list<string>  $parts
     */
    private function finalizeTransaction(string $ddmm, array $parts, int $year, int $lineOrder): ?ParsedBankStatementLine
    {
        for ($index = count($parts) - 1; $index >= 0; $index--) {
            $amountParse = $this->parseAmountLine($parts[$index]);
            if ($amountParse === null) {
                continue;
            }

            [$debit, $credit, $balance] = $amountParse;
            if (! $this->amountsComplete($debit, $credit, $balance)) {
                continue;
            }

            $descParts = $parts;
            array_splice($descParts, $index, 1);

            return $this->makeLine($ddmm, $descParts, $debit, $credit, $balance, $year, $lineOrder);
        }

        return null;
    }

    /**
     * @param  list<string>  $parts
     */
    private function buildDescription(array $parts): string
    {
        $filtered = [];

        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed === '' || preg_match('/^(KBB|HAR)$/', $trimmed)) {
                if (preg_match('/^(KBB|HAR)$/', $trimmed)) {
                    $filtered[] = $trimmed;
                }

                continue;
            }

            if ($this->parseAmountLine($trimmed) !== null) {
                continue;
            }

            $filtered[] = $trimmed;
        }

        return trim(preg_replace('/\s+/', ' ', implode(' ', $filtered)) ?? '');
    }

    private function extractReference(string $description): ?string
    {
        if (preg_match('/\b(\d{2}\d{2}\/FTSCY\/WS\d+)\b/i', $description, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\b(\d{10,})\b/', $description, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @return array{0: string, 1: float, 2: float, 3: ?float}|null
     */
    private function parseAmountTail(string $line): ?array
    {
        if (preg_match('/^(.+?)\s+((?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2})(?:\s*DB)?(?:\s+((?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2}))?$/', $line, $matches)) {
            $desc = trim($matches[1]);
            $first = $this->toFloat($matches[2]);
            $hasDb = preg_match('/\s*DB\b/u', $line) === 1;
            $balance = isset($matches[3]) ? $this->toFloat($matches[3]) : null;

            if ($hasDb) {
                return [$desc, $first, 0.0, $balance];
            }

            if ($balance !== null) {
                return [$desc, 0.0, $first, $balance];
            }

            return [$desc, 0.0, $first, null];
        }

        return null;
    }

    /**
     * @return array{0: float, 1: float, 2: ?float}|null
     */
    private function parseAmountLine(string $line): ?array
    {
        $trimmed = trim($line);

        if (! $this->looksLikeAmountLine($trimmed)) {
            return null;
        }

        if (preg_match('/^((?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2})\s*DB\s+((?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2})$/', $trimmed, $matches)) {
            return [$this->toFloat($matches[1]), 0.0, $this->toFloat($matches[2])];
        }

        if (preg_match('/^((?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2})\s*DB$/', $trimmed, $matches)) {
            return [$this->toFloat($matches[1]), 0.0, null];
        }

        if (preg_match('/^((?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2})\s+((?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2})$/', $trimmed, $matches)) {
            return [0.0, $this->toFloat($matches[1]), $this->toFloat($matches[2])];
        }

        if (preg_match('/^((?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2})$/', $trimmed, $matches)) {
            return [0.0, $this->toFloat($matches[1]), null];
        }

        return null;
    }

    private function looksLikeAmountLine(string $line): bool
    {
        return preg_match('/^(?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2}(?:\s*DB)?(?:\s+(?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2})?$/', $line) === 1;
    }

    private function amountsComplete(float $debit, float $credit, ?float $balance): bool
    {
        return $debit > 0.0 || $credit > 0.0;
    }

    private function toPostingDate(string $ddmm, int $year): string
    {
        [$day, $month] = array_map('intval', explode('/', $ddmm));

        return Carbon::create($year, $month, $day)->toDateString();
    }

    private function matchMoney(string $text, string $pattern): float
    {
        if (preg_match($pattern, $text, $matches)) {
            return $this->toFloat($matches[1]);
        }

        return 0.0;
    }

    private function toFloat(string $value): float
    {
        return (float) str_replace(',', '', $value);
    }
}
