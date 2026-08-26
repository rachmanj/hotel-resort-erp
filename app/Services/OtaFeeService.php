<?php

namespace App\Services;

use App\Enums\FolioItemType;
use App\Enums\OtaFeeChargeStatus;
use App\Enums\OtaFeeType;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\OtaFee;
use App\Models\OtaFeeCharge;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Accounting\GlPostingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OtaFeeService
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    /**
     * @return array{base_amount: float, fee_pct: float|null, fee_amount: float}
     */
    public function calculateForReservation(Reservation $reservation, OtaFee $otaFee): array
    {
        $folio = Folio::query()
            ->where('reservation_id', $reservation->id)
            ->where('type', 'master')
            ->first();

        if ($folio === null) {
            return ['base_amount' => 0.0, 'fee_pct' => null, 'fee_amount' => 0.0];
        }

        $baseAmount = (float) FolioItem::query()
            ->where('folio_id', $folio->id)
            ->where('item_type', FolioItemType::Room->value)
            ->sum('amount');

        if ($otaFee->fee_type === OtaFeeType::Percent) {
            $feePct = (float) $otaFee->base_fee_pct + (float) ($otaFee->variable_fee_pct ?? 0);
            $feeAmount = round($baseAmount * $feePct / 100, 2);

            return [
                'base_amount' => $baseAmount,
                'fee_pct' => $feePct,
                'fee_amount' => $feeAmount,
            ];
        }

        $nights = max(1, Carbon::parse($reservation->arrival_date)->diffInDays($reservation->departure_date));
        $feeAmount = round((float) $otaFee->flat_fee_per_room_night * $nights, 2);

        return [
            'base_amount' => $feeAmount,
            'fee_pct' => null,
            'fee_amount' => $feeAmount,
        ];
    }

    public function accrue(Reservation $reservation, OtaFee $otaFee, ?User $performedBy = null): OtaFeeCharge
    {
        if (OtaFeeCharge::query()->where('reservation_id', $reservation->id)->exists()) {
            return OtaFeeCharge::query()->where('reservation_id', $reservation->id)->firstOrFail();
        }

        return DB::transaction(function () use ($reservation, $otaFee): OtaFeeCharge {
            $folio = Folio::query()
                ->where('reservation_id', $reservation->id)
                ->where('type', 'master')
                ->first();

            $calculation = $this->calculateForReservation($reservation, $otaFee);

            if ($calculation['fee_amount'] <= 0) {
                throw new InvalidArgumentException('OTA fee amount must be greater than zero.');
            }

            $charge = OtaFeeCharge::query()->create([
                'hotel_id' => $reservation->hotel_id,
                'ota_fee_id' => $otaFee->id,
                'reservation_id' => $reservation->id,
                'folio_id' => $folio?->id,
                'base_amount' => $calculation['base_amount'],
                'fee_pct' => $calculation['fee_pct'],
                'fee_amount' => $calculation['fee_amount'],
                'status' => OtaFeeChargeStatus::Pending->value,
                'earned_at' => now(),
            ]);

            $expenseAccount = $this->glPostingService->findAccountByCode($reservation->hotel_id, '6-3400');
            $payableAccount = $this->glPostingService->findAccountByCode($reservation->hotel_id, '2-1410');

            $this->glPostingService->post([
                [
                    'hotel_id' => $reservation->hotel_id,
                    'chart_of_account_id' => $expenseAccount->id,
                    'transaction_date' => now()->toDateString(),
                    'debit' => $calculation['fee_amount'],
                    'credit' => 0,
                    'description' => "OTA fee accrual · {$otaFee->name} ({$reservation->reservation_code})",
                    'reference_number' => $reservation->reservation_code,
                    'source_type' => OtaFeeCharge::class,
                    'source_id' => $charge->id,
                ],
                [
                    'hotel_id' => $reservation->hotel_id,
                    'chart_of_account_id' => $payableAccount->id,
                    'transaction_date' => now()->toDateString(),
                    'debit' => 0,
                    'credit' => $calculation['fee_amount'],
                    'description' => "OTA fee accrual · {$otaFee->name} ({$reservation->reservation_code})",
                    'reference_number' => $reservation->reservation_code,
                    'source_type' => OtaFeeCharge::class,
                    'source_id' => $charge->id,
                ],
            ]);

            return $charge;
        });
    }
}
