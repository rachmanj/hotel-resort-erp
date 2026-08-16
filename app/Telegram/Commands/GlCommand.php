<?php

namespace App\Telegram\Commands;

use App\Models\GeneralLedger;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Services\Accounting\GlPostingService;
use App\Telegram\TelegramResponder;

class GlCommand extends BaseCommand
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

        if ($args === []) {
            $this->reply($tgUser, 'Usage: /gl {account_code}');

            return;
        }

        $accountCode = $args[0];

        try {
            $account = $this->glPostingService->findAccountByCode((int) $tgUser->hotel_id, $accountCode);
        } catch (\InvalidArgumentException) {
            $this->reply($tgUser, "Account {$accountCode} not found.");

            return;
        }

        $balance = $this->glPostingService->getBalance($account, null, (int) $tgUser->hotel_id);

        $entries = GeneralLedger::query()
            ->where('chart_of_account_id', $account->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $lines = [
            "📒 *GL: {$account->account_code}*",
            "{$account->name}",
            'Balance: *'.$this->formatIdr($balance).'*',
            '',
            '*Recent transactions:*',
        ];

        if ($entries->isEmpty()) {
            $lines[] = '_No transactions yet._';
        } else {
            foreach ($entries as $entry) {
                $amount = (float) $entry->debit > 0
                    ? 'Dr '.$this->formatIdr($entry->debit)
                    : 'Cr '.$this->formatIdr($entry->credit);
                $lines[] = "{$entry->transaction_date->format('d M')} · {$amount}";
                $lines[] = "  {$entry->description}";
            }
        }

        $this->reply($tgUser, implode("\n", $lines));
    }
}
