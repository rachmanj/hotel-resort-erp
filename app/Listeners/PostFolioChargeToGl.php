<?php

namespace App\Listeners;

use App\Enums\FolioItemType;
use App\Events\FolioItemPosted;
use App\Models\ChartOfAccount;
use App\Services\Accounting\GlPostingService;

class PostFolioChargeToGl
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    public function handle(FolioItemPosted $event): void
    {
        $item = $event->folioItem->loadMissing('folio');
        $folio = $item->folio;

        if ($folio === null) {
            return;
        }

        $hotelId = (int) $folio->hotel_id;
        $total = round((float) $item->amount + (float) $item->tax_amount + (float) $item->service_charge_amount, 2);

        if ($total <= 0) {
            return;
        }

        $transactionDate = ($item->posted_at ?? now())->toDateString();
        $reference = $folio->folio_no;
        $sourceType = 'folio_item';
        $sourceId = $item->id;

        $guestLedger = $this->glPostingService->findAccountByCode($hotelId, '1-1300');
        $ppnPayable = $this->glPostingService->findAccountByCode($hotelId, '2-2100');
        $serviceChargeRevenue = $this->glPostingService->findAccountByCode($hotelId, '4-1500');

        $lines = [];
        $itemType = $item->item_type instanceof FolioItemType ? $item->item_type->value : (string) $item->item_type;

        if (in_array($itemType, [FolioItemType::Discount->value, 'discount'], true)) {
            $revenueAccount = $this->resolveRevenueAccount($hotelId, $itemType);
            $lines[] = $this->line($hotelId, $revenueAccount->id, $transactionDate, $total, 0, "Discount: {$item->description}", $reference, $sourceType, $sourceId, $item->department_id);
            $lines[] = $this->line($hotelId, $guestLedger->id, $transactionDate, 0, $total, "Discount: {$item->description}", $reference, $sourceType, $sourceId, $item->department_id);

            $this->glPostingService->post($lines);

            return;
        }

        if (in_array($itemType, [FolioItemType::Tax->value, 'tax'], true)) {
            return;
        }

        $revenueAccount = $this->resolveRevenueAccount($hotelId, $itemType);
        $revenueAmount = round((float) $item->amount, 2);
        $taxAmount = round((float) $item->tax_amount, 2);
        $serviceChargeAmount = round((float) $item->service_charge_amount, 2);

        $lines[] = $this->line($hotelId, $guestLedger->id, $transactionDate, $total, 0, $item->description, $reference, $sourceType, $sourceId, $item->department_id);

        if ($revenueAmount > 0) {
            $lines[] = $this->line($hotelId, $revenueAccount->id, $transactionDate, 0, $revenueAmount, $item->description, $reference, $sourceType, $sourceId, $item->department_id);
        }

        if ($serviceChargeAmount > 0) {
            $lines[] = $this->line($hotelId, $serviceChargeRevenue->id, $transactionDate, 0, $serviceChargeAmount, "Service charge: {$item->description}", $reference, $sourceType, $sourceId, $item->department_id);
        }

        if ($taxAmount > 0) {
            $lines[] = $this->line($hotelId, $ppnPayable->id, $transactionDate, 0, $taxAmount, "PPN: {$item->description}", $reference, $sourceType, $sourceId, $item->department_id);
        }

        $this->glPostingService->post($lines);
    }

    private function resolveRevenueAccount(int $hotelId, string $itemType): ChartOfAccount
    {
        $code = match ($itemType) {
            FolioItemType::Room->value, 'room' => '4-1100',
            FolioItemType::Fb->value, 'fb' => '4-2100',
            FolioItemType::Spa->value, 'spa' => '4-3100',
            FolioItemType::ServiceCharge->value, 'service_charge' => '4-1500',
            default => '4-9000',
        };

        return $this->glPostingService->findAccountByCode($hotelId, $code);
    }

    /**
     * @return array<string, mixed>
     */
    private function line(
        int $hotelId,
        int $accountId,
        string $transactionDate,
        float $debit,
        float $credit,
        string $description,
        string $reference,
        string $sourceType,
        int $sourceId,
        ?int $departmentId = null,
    ): array {
        return [
            'hotel_id' => $hotelId,
            'chart_of_account_id' => $accountId,
            'department_id' => $departmentId,
            'transaction_date' => $transactionDate,
            'debit' => $debit,
            'credit' => $credit,
            'description' => $description,
            'reference_number' => $reference,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ];
    }
}
