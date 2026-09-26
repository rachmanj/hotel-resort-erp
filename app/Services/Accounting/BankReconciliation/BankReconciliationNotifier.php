<?php

namespace App\Services\Accounting\BankReconciliation;

use App\Models\BankReconciliation;
use App\Models\User;
use App\Notifications\BankReconciliationRejectedNotification;
use App\Notifications\BankReconciliationSubmittedNotification;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;

class BankReconciliationNotifier
{
    public function notifyValidators(BankReconciliation $reconciliation, User $actor): void
    {
        $reconciliation->loadMissing('bankAccount');
        $hotelId = (int) $reconciliation->bankAccount->hotel_id;

        $recipients = $this->validatorCandidates()
            ->filter(fn (User $user): bool => $user->id !== $actor->id)
            ->filter(fn (User $user): bool => ! $reconciliation->isPreparer($user->id))
            ->filter(fn (User $user): bool => $user->canAccessHotel($hotelId))
            ->unique('id');

        foreach ($recipients as $recipient) {
            $recipient->notify(new BankReconciliationSubmittedNotification($reconciliation, $actor));
        }
    }

    public function notifyPreparer(BankReconciliation $reconciliation, User $rejector, string $reason): void
    {
        $preparerIds = array_values(array_unique(array_filter([
            $reconciliation->created_by,
            $reconciliation->submitted_by,
        ])));

        if ($preparerIds === []) {
            return;
        }

        $preparers = User::query()->whereIn('id', $preparerIds)->get();

        foreach ($preparers as $preparer) {
            if ($preparer->id === $rejector->id) {
                continue;
            }

            $preparer->notify(new BankReconciliationRejectedNotification($reconciliation, $rejector, $reason));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function validatorCandidates(): Collection
    {
        $permissionExists = Permission::query()
            ->where('name', 'bankrec.validate')
            ->where('guard_name', config('auth.defaults.guard', 'web'))
            ->exists();

        if ($permissionExists) {
            return User::permission('bankrec.validate')->get();
        }

        return User::role(['admin', 'finance', 'manager'])->get();
    }
}
