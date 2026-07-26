<?php

namespace App\Telegram\Commands;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Services\Accounting\GlPostingService;
use App\Telegram\TelegramResponder;

class PnlCommand extends BaseCommand
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

        $statement = $this->glPostingService->getIncomeStatement(
            (int) $tgUser->hotel_id,
            now()->startOfMonth(),
            now()->endOfMonth(),
        );

        $lines = [
            '📈 *Laba Rugi (P&L)*',
            now()->format('M Y'),
            '',
            '*Revenue*',
        ];

        foreach ($statement['revenue']->take(5) as $row) {
            $lines[] = "{$row['account_code']}: {$this->formatIdr($row['amount'])}";
        }
        $lines[] = "Total Revenue: *{$this->formatIdr($statement['total_revenue'])}*";
        $lines[] = '';
        $lines[] = "COGS: {$this->formatIdr($statement['total_cogs'])}";
        $lines[] = "Gross Profit: *{$this->formatIdr($statement['gross_profit'])}*";
        $lines[] = "Expenses: {$this->formatIdr($statement['total_expenses'])}";
        $lines[] = '';
        $lines[] = "*Net Income: {$this->formatIdr($statement['net_income'])}*";

        $this->reply($tgUser, implode("\n", $lines));
    }
}
