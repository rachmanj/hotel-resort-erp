<?php

namespace App\Services\Accounting\BankReconciliation\Statement;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class BankStatementAiReader
{
    public function isEnabled(): bool
    {
        $key = config('bank_reconciliation.ai.key');

        return is_string($key) && $key !== '';
    }

    public function parse(string $text, string $profileHint = 'unknown'): ParsedBankStatement
    {
        if (! $this->isEnabled()) {
            throw new InvalidArgumentException(
                'AI bank statement extraction is disabled because BANK_RECON_AI_KEY is not configured.',
            );
        }

        $baseUrl = rtrim((string) config('bank_reconciliation.ai.base_url'), '/');
        $model = (string) config('bank_reconciliation.ai.model');

        if ($baseUrl === '') {
            throw new InvalidArgumentException('BANK_RECON_AI_BASE_URL is not configured.');
        }

        $response = Http::withToken((string) config('bank_reconciliation.ai.key'))
            ->timeout(120)
            ->post($baseUrl.'/chat/completions', [
                'model' => $model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You extract bank statement data from plain text. Return JSON only with keys: account_number, period_start, period_end, opening_balance, closing_balance, total_debit, total_credit, debit_count, credit_count, lines (array of posting_date, description, reference, debit, credit, balance, line_order), unparsed_rows (count, reasons). Bank polarity: debit = money out, credit = money in.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Profile hint: {$profileHint}\n\nStatement text:\n".$text,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new InvalidArgumentException('AI bank statement extraction failed: '.$response->body());
        }

        $payload = $response->json();
        $content = $payload['choices'][0]['message']['content'] ?? null;

        if (! is_string($content) || $content === '') {
            throw new InvalidArgumentException('AI bank statement extraction returned an empty response.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException('AI bank statement extraction returned invalid JSON.');
        }

        $lines = [];
        foreach ($decoded['lines'] ?? [] as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $lines[] = new ParsedBankStatementLine(
                postingDate: (string) ($row['posting_date'] ?? ''),
                description: (string) ($row['description'] ?? ''),
                reference: isset($row['reference']) ? (string) $row['reference'] : null,
                debit: (float) ($row['debit'] ?? 0),
                credit: (float) ($row['credit'] ?? 0),
                balance: isset($row['balance']) ? (float) $row['balance'] : null,
                lineOrder: (int) ($row['line_order'] ?? $index),
            );
        }

        $unparsed = $decoded['unparsed_rows'] ?? [];

        return new ParsedBankStatement(
            profileCode: 'pdf_ai',
            accountNumber: (string) ($decoded['account_number'] ?? ''),
            periodStart: (string) ($decoded['period_start'] ?? ''),
            periodEnd: (string) ($decoded['period_end'] ?? ''),
            openingBalance: (float) ($decoded['opening_balance'] ?? 0),
            closingBalance: (float) ($decoded['closing_balance'] ?? 0),
            totalDebit: (float) ($decoded['total_debit'] ?? 0),
            totalCredit: (float) ($decoded['total_credit'] ?? 0),
            debitCount: (int) ($decoded['debit_count'] ?? 0),
            creditCount: (int) ($decoded['credit_count'] ?? 0),
            lines: $lines,
            unparsedRowCount: (int) ($unparsed['count'] ?? 0),
            unparsedReasons: is_array($unparsed['reasons'] ?? null) ? $unparsed['reasons'] : [],
            aiMeta: [
                'raw_response' => $payload,
                'parsed_json' => $decoded,
            ],
        );
    }
}
