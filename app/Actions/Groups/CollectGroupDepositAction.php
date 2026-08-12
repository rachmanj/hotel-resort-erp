<?php

namespace App\Actions\Groups;

use App\Models\ReservationGroup;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\FolioPostingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CollectGroupDepositAction
{
    public function __construct(
        private FolioPostingService $folioPostingService,
    ) {}

    public function __invoke(
        ReservationGroup $group,
        float $amount,
        string $method,
        ?string $referenceNo,
        User $receivedBy,
    ): ReservationGroup {
        return DB::transaction(function () use ($group, $amount, $method, $referenceNo, $receivedBy): ReservationGroup {
            if ($amount <= 0) {
                throw new InvalidArgumentException('Deposit amount must be greater than zero.');
            }

            $guestId = $group->pic_guest_id;

            if ($guestId === null) {
                $firstReservation = $group->reservations()->first();
                $guestId = $firstReservation?->guest_id;
            }

            if ($guestId === null) {
                throw new InvalidArgumentException('Group must have a PIC guest or member reservation to collect deposit.');
            }

            $depositFolio = $this->folioPostingService->findOrCreateGroupDepositFolio($group, $guestId);

            $this->folioPostingService->postPayment(
                $depositFolio,
                $amount,
                $method,
                $referenceNo,
                $receivedBy,
            );

            $group->update([
                'deposit_amount' => (float) $group->deposit_amount + $amount,
                'deposit_paid_at' => now(),
            ]);

            ActivityLogObserver::logCustom(
                $group,
                'deposit_collected',
                'Deposit Rp'.number_format($amount, 0, ',', '.')." collected for group {$group->group_code} by {$receivedBy->name}",
                $receivedBy->id,
            );

            return $group->fresh();
        });
    }
}
