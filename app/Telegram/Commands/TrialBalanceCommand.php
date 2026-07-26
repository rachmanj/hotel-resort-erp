<?php

namespace App\Telegram\Commands;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Services\Accounting\GlPostingService;
use App\Telegram\TelegramResponder;

class TrialBalanceCommand extends BaseCommand
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

        $rows = $this->glPostingService->getTrialBalance((int) $tgUser->hotel_id, now());
        $totalDebit = round($rows->sum('debit'), 2);
        $totalCredit = round($rows->sum('credit'), 2);

        $lines = [
            '📊 *Trial Balance*',
            'As of: '.now()->format('d M Y'),
            '',
        ];

        foreach ($rows->take(15) as $row) {
            $lines[] = "{$row['account_code']} {$row['account_name']}";
            $lines[] = "  Dr {$this->formatIdr($row['debit'])} | Cr {$this->formatIdr($row['credit'])}";
        }

        if ($rows->count() > 15) {
            $lines[] = '_...and '.($rows->count() - 15).' more accounts_';
        }

        $lines[] = '';
        $lines[] = "*Total Dr:* {$this->formatIdr($totalDebit)}";
        $lines[] = "*Total Cr:* {$this->formatIdr($totalCredit)}";

        $this->reply($tgUser, implode("\n", $lines));
    }
}
