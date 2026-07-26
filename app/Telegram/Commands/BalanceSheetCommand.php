<?php

namespace App\Telegram\Commands;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Services\Accounting\GlPostingService;
use App\Telegram\TelegramResponder;

class BalanceSheetCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private GlPostingService $glPostingService,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return $tgUser->isLinked() && ($tgUser->user?->can('accounting.view') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        $this->setHotelContext($tgUser);

        $statement = $this->glPostingService->getBalanceSheet((int) $tgUser->hotel_id, now());

        $lines = [
            '🏦 *Neraca (Balance Sheet)*',
            'As of: '.now()->format('d M Y'),
            '',
            '*Assets*',
        ];

        foreach ($statement['assets']->take(8) as $row) {
            $lines[] = "{$row['account_code']}: {$this->formatIdr($row['amount'])}";
        }
        $lines[] = "Total Assets: *{$this->formatIdr($statement['total_assets'])}*";
        $lines[] = '';
        $lines[] = '*Liabilities*';
        foreach ($statement['liabilities']->take(5) as $row) {
            $lines[] = "{$row['account_code']}: {$this->formatIdr($row['amount'])}";
        }
        $lines[] = "Total Liabilities: *{$this->formatIdr($statement['total_liabilities'])}*";
        $lines[] = '';
        $lines[] = '*Equity*';
        foreach ($statement['equity']->take(5) as $row) {
            $lines[] = "{$row['account_code']}: {$this->formatIdr($row['amount'])}";
        }
        $lines[] = "Total Equity: *{$this->formatIdr($statement['total_equity'])}*";

        $this->reply($tgUser, implode("\n", $lines));
    }
}
