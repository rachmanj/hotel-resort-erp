<?php

namespace App\Services\Accounting\BankReconciliation\Statement;

use Carbon\Carbon;

class MandiriKopraPdfParser implements BankStatementProfileParser
{
    public function code(): string
    {
        return 'mandiri_kopra_pdf';
    }

    public function supports(string $text): bool
    {
        return str_contains($text, 'Account Statement')
            && str_contains($text, 'Account No.');
    }

    public function parse(string $text): ParsedBankStatement
    {
        $accountNumber = $this->extractAccountNumber($text);
        [$periodStart, $periodEnd] = $this->extractPeriod($text);
        [$opening, $closing, $totalDebit, $totalCredit, $debitCount, $creditCount] = $this->extractSummary($text);
        [$lines, $unparsedReasons] = $this->extractLines($text);

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
        if (preg_match('/Account No\.\s*Account Name.*?\n\s*(\d{10,})/s', $text, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\n(\d{13})\s+PRATASABA/', $text, $matches)) {
            return $matches[1];
        }

        if (preg_match('/\n(\d{13})\s+[A-Z]/', $text, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function extractPeriod(string $text): array
    {
        if (preg_match('/(\d{2}\s+[A-Z][a-z]{2}\s+\d{4})\s*-\s*(\d{2}\s+[A-Z][a-z]{2}\s+\d{4})/', $text, $matches)) {
            $start = Carbon::parse($matches[1]);
            $end = Carbon::parse($matches[2]);

            return [$start->toDateString(), $end->toDateString()];
        }

        $now = now();

        return [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()];
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float, 4: int, 5: int}
     */
    private function extractSummary(string $text): array
    {
        $opening = $this->matchMoney($text, '/Opening Balance[^\d]*([\d,]+\.\d{2})/');
        $closing = $this->matchMoney($text, '/Closing Balance[^\d]*([\d,]+\.\d{2})/');
        $totalDebit = $this->matchMoney($text, '/Total Amount Debited[^\d]*(\d+)\s+([\d,]+\.\d{2})/', 2);
        $debitCount = (int) $this->matchMoney($text, '/Total Amount Debited[^\d]*(\d+)\s+([\d,]+\.\d{2})/', 1);
        $totalCredit = $this->matchMoney($text, '/Total Amount Credited[^\d]*(\d+)\s+([\d,]+\.\d{2})/', 2);
        $creditCount = (int) $this->matchMoney($text, '/Total Amount Credited[^\d]*(\d+)\s+([\d,]+\.\d{2})/', 1);

        if ($opening === 0.0 && preg_match('/Opening Balance\s+Total Amount DebitedNo\. of Debit\s+([\d,]+\.\d{2})/', $text, $matches)) {
            $opening = $this->toFloat($matches[1]);
        }

        if ($closing === 0.0 && preg_match('/Closing Balance\s+Total Amount CreditedNo\. of Credit\s+([\d,]+\.\d{2})/', $text, $matches)) {
            $closing = $this->toFloat($matches[1]);
        }

        if ($debitCount === 0 && preg_match('/Opening Balance\s+Total Amount DebitedNo\. of Debit\s+[\d,]+\.\d{2}\s+(\d+)\s+([\d,]+\.\d{2})/', $text, $matches)) {
            $debitCount = (int) $matches[1];
            $totalDebit = $this->toFloat($matches[2]);
        }

        if ($creditCount === 0 && preg_match('/Closing Balance\s+Total Amount CreditedNo\. of Credit\s+[\d,]+\.\d{2}\s+(\d+)\s+([\d,]+\.\d{2})/', $text, $matches)) {
            $creditCount = (int) $matches[1];
            $totalCredit = $this->toFloat($matches[2]);
        }

        return [$opening, $closing, $totalDebit, $totalCredit, $debitCount, $creditCount];
    }

    /**
     * @return array{0: list<ParsedBankStatementLine>, 1: list<string>}
     */
    private function extractLines(string $text): array
    {
        $lines = [];
        $unparsed = [];
        $lineOrder = 0;

        $rawLines = preg_split('/\R/', $text) ?: [];
        $blocks = [];
        $currentDate = null;
        $currentLines = [];

        foreach ($rawLines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '' || $this->isNoiseLine($line)) {
                continue;
            }

            if (preg_match('/^(\d{2}\s+[A-Z][a-z]{2}\s+\d{4}),$/', $line, $dateMatch)) {
                if ($currentDate !== null) {
                    $blocks[] = ['date' => $currentDate, 'lines' => $currentLines];
                }

                $currentDate = Carbon::parse($dateMatch[1])->toDateString();
                $currentLines = [];

                continue;
            }

            if ($currentDate === null) {
                continue;
            }

            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $line)) {
                continue;
            }

            $currentLines[] = $line;
        }

        if ($currentDate !== null) {
            $blocks[] = ['date' => $currentDate, 'lines' => $currentLines];
        }

        foreach ($blocks as $block) {
            $parsed = $this->parseBlock($block['date'], $block['lines'], $lineOrder);
            if ($parsed === null) {
                $unparsed[] = 'Could not split Mandiri row for '.$block['date'].': '.mb_substr(implode(' ', $block['lines']), 0, 120);

                continue;
            }

            $lines[] = $parsed;
            $lineOrder++;
        }

        return [$lines, $unparsed];
    }

    /**
     * @param  list<string>  $blockLines
     */
    private function parseBlock(string $postingDate, array $blockLines, int $lineOrder): ?ParsedBankStatementLine
    {
        if ($blockLines === []) {
            return null;
        }

        for ($index = count($blockLines) - 1; $index >= 0; $index--) {
            $amounts = $this->parseAmountsLine($blockLines[$index]);
            if ($amounts === null) {
                continue;
            }

            [$debit, $credit, $balance] = $amounts;
            $descriptionLines = array_slice($blockLines, 0, $index);
            $amountLine = $blockLines[$index];
            $inlineDescription = $this->descriptionFromAmountLine($amountLine);
            $description = trim(preg_replace('/\s+/', ' ', implode(' ', [...$descriptionLines, $inlineDescription]) ?? ''));
            $reference = $this->extractReference([...$descriptionLines, $amountLine]);

            if ($description === '') {
                return null;
            }

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

        return null;
    }

    /**
     * @param  list<string>  $lines
     */
    private function extractReference(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^(\d{10,})/', $line, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function isNoiseLine(string $line): bool
    {
        return preg_match('/^Account Statement|^Created |^Account Statement Summary|^Opening Balance|^Closing Balance|^Account No\.|^Period Currency|^CreditReference|^Page \d+ of|^koprabymandiri|^For further questions/i', $line) === 1;
    }

    /**
     * @return array{0: float, 1: float, 2: float}|null
     */
    private function descriptionFromAmountLine(string $line): string
    {
        if (preg_match('/^(.*)\s+-\s+(?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2}\s+(?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2}\s+(?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2}$/', $line, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function parseAmountsLine(string $line): ?array
    {
        if (preg_match('/-\s*((?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2})\s+((?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2})\s+((?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2})$/', $line, $matches)) {
            return [$this->toFloat($matches[1]), $this->toFloat($matches[2]), $this->toFloat($matches[3])];
        }

        if (! preg_match_all('/((?:\d{1,3}(?:,\d{3})+|\d{1,6})\.\d{2})/', $line, $matches) || count($matches[1]) < 3) {
            return null;
        }

        $values = array_slice($matches[1], -3);

        return [$this->toFloat($values[0]), $this->toFloat($values[1]), $this->toFloat($values[2])];
    }

    private function matchMoney(string $text, string $pattern, int $group = 1): float
    {
        if (preg_match($pattern, $text, $matches)) {
            return $this->toFloat($matches[$group]);
        }

        return 0.0;
    }

    private function toFloat(string $value): float
    {
        return (float) str_replace(',', '', $value);
    }
}
