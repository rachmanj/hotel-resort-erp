<?php

namespace App\Services;

use App\Enums\FolioItemType;
use App\Enums\FolioStatus;
use App\Enums\FolioType;
use App\Events\FolioItemPosted;
use App\Events\PaymentReceived;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\ReservationGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FolioPostingService
{
    public function __construct(
        private TaxCalculator $taxCalculator,
    ) {}

    public function postCharge(
        Folio $folio,
        string $itemType,
        string $description,
        float $amount,
        float $quantity = 1,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?User $postedBy = null,
        bool $applyTax = true,
    ): FolioItem {
        if ($folio->status !== FolioStatus::Open) {
            throw new InvalidArgumentException('Cannot post charges to a closed or voided folio.');
        }

        $unitPrice = $amount;
        $lineAmount = round($quantity * $unitPrice, 2);

        $taxAmount = 0.0;
        $serviceChargeAmount = 0.0;

        if ($applyTax && ! in_array($itemType, ['tax', 'service_charge', 'discount', 'deposit_credit'], true)) {
            $taxes = $this->taxCalculator->calculate($lineAmount, $this->mapItemTypeToAppliesTo($itemType));
            $taxAmount = $taxes['tax'];
            $serviceChargeAmount = $taxes['service_charge'];
        }

        $folioItem = FolioItem::query()->create([
            'folio_id' => $folio->id,
            'item_type' => $itemType,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => $lineAmount,
            'tax_amount' => $taxAmount,
            'service_charge_amount' => $serviceChargeAmount,
            'posted_by' => $postedBy?->id,
            'posted_at' => now(),
        ]);

        FolioItemPosted::dispatch($folioItem);

        return $folioItem;
    }

    public function postPayment(
        Folio $folio,
        float $amount,
        string $method,
        ?string $referenceNo,
        User $receivedBy,
        ?string $originalCurrencyCode = null,
        ?float $originalAmount = null,
        ?int $exchangeRateId = null,
    ): Payment {
        if ($folio->status !== FolioStatus::Open) {
            throw new InvalidArgumentException('Cannot post payments to a closed or voided folio.');
        }

        $payment = Payment::query()->create([
            'folio_id' => $folio->id,
            'amount' => round($amount, 2),
            'method' => $method,
            'reference_no' => $referenceNo,
            'original_currency_code' => $originalCurrencyCode,
            'original_amount' => $originalAmount,
            'exchange_rate_id' => $exchangeRateId,
            'received_by' => $receivedBy->id,
            'paid_at' => now(),
            'is_refund' => false,
        ]);

        PaymentReceived::dispatch($payment);

        return $payment;
    }

    public function getBalance(Folio $folio): float
    {
        $charges = FolioItem::query()
            ->where('folio_id', $folio->id)
            ->selectRaw('SUM(amount + tax_amount + service_charge_amount) as total')
            ->value('total') ?? 0;

        $payments = Payment::query()
            ->where('folio_id', $folio->id)
            ->selectRaw('SUM(CASE WHEN is_refund THEN -amount ELSE amount END) as total')
            ->value('total') ?? 0;

        return round((float) $charges - (float) $payments, 2);
    }

    public function getChargesTotal(Folio $folio): float
    {
        return (float) (FolioItem::query()
            ->where('folio_id', $folio->id)
            ->selectRaw('SUM(amount + tax_amount + service_charge_amount) as total')
            ->value('total') ?? 0);
    }

    public function closeFolio(Folio $folio): void
    {
        if ($folio->status !== FolioStatus::Open) {
            throw new InvalidArgumentException('Folio is not open.');
        }

        $folio->update([
            'status' => FolioStatus::Closed->value,
            'closed_at' => now(),
        ]);
    }

    public function findOrCreateMasterFolio(
        int $hotelId,
        int $reservationId,
        int $guestId,
        ?int $companyId = null,
    ): Folio {
        $existing = Folio::query()
            ->where('reservation_id', $reservationId)
            ->where('type', FolioType::Master->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Folio::query()->create([
            'hotel_id' => $hotelId,
            'folio_no' => $this->generateFolioNumber(),
            'reservation_id' => $reservationId,
            'guest_id' => $guestId,
            'company_id' => $companyId,
            'type' => FolioType::Master->value,
            'status' => FolioStatus::Open->value,
            'opened_at' => now(),
        ]);
    }

    public function findOrCreateGroupDepositFolio(ReservationGroup $group, int $guestId): Folio
    {
        $existing = Folio::query()
            ->where('reservation_group_id', $group->id)
            ->where('type', FolioType::GroupDeposit->value)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Folio::query()->create([
            'hotel_id' => $group->hotel_id,
            'folio_no' => $this->generateFolioNumber(),
            'reservation_group_id' => $group->id,
            'guest_id' => $guestId,
            'company_id' => $group->company_id,
            'type' => FolioType::GroupDeposit->value,
            'status' => FolioStatus::Open->value,
            'opened_at' => now(),
        ]);
    }

    private function generateFolioNumber(): string
    {
        return DB::transaction(function (): string {
            $datePrefix = now()->format('Ymd');
            $prefix = "FOL-{$datePrefix}-";

            $lastCode = Folio::query()
                ->withoutGlobalScope('hotel')
                ->where('folio_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('folio_no')
                ->value('folio_no');

            $sequence = 1;
            if ($lastCode !== null) {
                $sequence = (int) substr($lastCode, -4) + 1;
            }

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }

    private function mapItemTypeToAppliesTo(string $itemType): string
    {
        return match ($itemType) {
            FolioItemType::Room->value, 'room' => 'room',
            FolioItemType::Fb->value, 'fb' => 'fb',
            FolioItemType::Spa->value, 'spa' => 'spa',
            default => 'all',
        };
    }
}
