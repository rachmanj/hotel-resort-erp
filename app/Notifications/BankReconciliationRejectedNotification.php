<?php

namespace App\Notifications;

use App\Models\BankReconciliation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BankReconciliationRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public BankReconciliation $reconciliation,
        public User $rejectedBy,
        public string $reason,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->reconciliation->loadMissing('bankAccount');

        $bankName = $this->reconciliation->bankAccount->bank_name;
        $accountNo = $this->reconciliation->bankAccount->account_no;
        $period = $this->periodLabel();

        return (new MailMessage)
            ->subject("Bank reconciliation rejected — {$bankName} {$period}")
            ->greeting('Hello '.$notifiable->name.',')
            ->line("{$this->rejectedBy->name} rejected your bank reconciliation submission.")
            ->line("Reason: {$this->reason}")
            ->line("Bank: {$bankName}")
            ->line("Account: {$accountNo}")
            ->line("Period: {$period}")
            ->action('Open reconciliation', $this->reconciliationUrl());
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(object $notifiable): array
    {
        $this->reconciliation->loadMissing('bankAccount');

        return [
            'bank_reconciliation_id' => $this->reconciliation->id,
            'bank_name' => $this->reconciliation->bankAccount->bank_name,
            'account_number' => $this->reconciliation->bankAccount->account_no,
            'period' => $this->periodLabel(),
            'rejected_by_name' => $this->rejectedBy->name,
            'reason' => $this->reason,
            'url' => $this->reconciliationUrl(),
        ];
    }

    private function periodLabel(): string
    {
        if ($this->reconciliation->period_end_date !== null) {
            return $this->reconciliation->period_end_date->format('Y-m');
        }

        return $this->reconciliation->periode?->format('Y-m') ?? '-';
    }

    private function reconciliationUrl(): string
    {
        return route('bank-rec.reconcile', $this->reconciliation);
    }
}
