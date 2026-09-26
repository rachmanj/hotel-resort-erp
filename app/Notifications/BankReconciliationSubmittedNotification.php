<?php

namespace App\Notifications;

use App\Models\BankReconciliation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BankReconciliationSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public BankReconciliation $reconciliation,
        public User $submittedBy,
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
            ->subject("Bank reconciliation submitted for validation — {$bankName} {$period}")
            ->greeting('Hello '.$notifiable->name.',')
            ->line("{$this->submittedBy->name} submitted a bank reconciliation for your review.")
            ->line("Bank: {$bankName}")
            ->line("Account: {$accountNo}")
            ->line("Period: {$period}")
            ->action('Review reconciliation', $this->reconciliationUrl());
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
            'submitted_by_name' => $this->submittedBy->name,
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
