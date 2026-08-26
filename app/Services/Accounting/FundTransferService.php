<?php

namespace App\Services\Accounting;

use App\Models\FundTransfer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FundTransferService
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    public function execute(
        int $hotelId,
        int $fromChartOfAccountId,
        int $toChartOfAccountId,
        float $amount,
        string $transferDate,
        string $description,
        ?int $fromBankAccountId = null,
        ?int $toBankAccountId = null,
        ?int $createdBy = null,
    ): FundTransfer {
        if ($fromChartOfAccountId === $toChartOfAccountId) {
            throw new InvalidArgumentException('From and to accounts must be different.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Transfer amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $hotelId,
            $fromChartOfAccountId,
            $toChartOfAccountId,
            $amount,
            $transferDate,
            $description,
            $fromBankAccountId,
            $toBankAccountId,
            $createdBy,
        ): FundTransfer {
            $transfer = FundTransfer::query()->create([
                'hotel_id' => $hotelId,
                'transfer_no' => $this->generateTransferNumber(),
                'from_chart_of_account_id' => $fromChartOfAccountId,
                'to_chart_of_account_id' => $toChartOfAccountId,
                'from_bank_account_id' => $fromBankAccountId,
                'to_bank_account_id' => $toBankAccountId,
                'amount' => $amount,
                'transfer_date' => $transferDate,
                'description' => $description,
                'created_by' => $createdBy,
            ]);

            $this->postToGl($transfer);

            return $transfer;
        });
    }

    public function postToGl(FundTransfer $transfer): Collection
    {
        $amount = round((float) $transfer->amount, 2);

        return $this->glPostingService->post([
            [
                'hotel_id' => (int) $transfer->hotel_id,
                'chart_of_account_id' => $transfer->to_chart_of_account_id,
                'transaction_date' => $transfer->transfer_date->toDateString(),
                'debit' => $amount,
                'credit' => 0,
                'description' => $transfer->description,
                'reference_number' => $transfer->transfer_no,
                'source_type' => 'fund_transfer',
                'source_id' => $transfer->id,
            ],
            [
                'hotel_id' => (int) $transfer->hotel_id,
                'chart_of_account_id' => $transfer->from_chart_of_account_id,
                'transaction_date' => $transfer->transfer_date->toDateString(),
                'debit' => 0,
                'credit' => $amount,
                'description' => $transfer->description,
                'reference_number' => $transfer->transfer_no,
                'source_type' => 'fund_transfer',
                'source_id' => $transfer->id,
            ],
        ]);
    }

    private function generateTransferNumber(): string
    {
        return DB::transaction(function (): string {
            $prefix = 'TRF-'.now()->format('Ym').'-';

            $lastNo = FundTransfer::query()
                ->withoutGlobalScope('hotel')
                ->where('transfer_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('transfer_no')
                ->value('transfer_no');

            $sequence = 1;
            if ($lastNo !== null) {
                $sequence = (int) substr($lastNo, -4) + 1;
            }

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
