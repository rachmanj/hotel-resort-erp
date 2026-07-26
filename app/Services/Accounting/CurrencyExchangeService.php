<?php

namespace App\Services\Accounting;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class CurrencyExchangeService
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    public function convert(float $amount, string $fromCurrency, ?CarbonInterface $asOf = null): float
    {
        if ($fromCurrency === $this->baseCurrencyCode()) {
            return round($amount, 2);
        }

        $rate = $this->resolveRate($fromCurrency, $asOf);

        return round($amount * (float) $rate->rate_to_base, 2);
    }

    public function resolveRate(string $fromCurrency, ?CarbonInterface $asOf = null): ExchangeRate
    {
        if ($fromCurrency === $this->baseCurrencyCode()) {
            throw new InvalidArgumentException('Base currency does not require an exchange rate.');
        }

        $currency = Currency::query()->where('code', $fromCurrency)->where('is_active', true)->firstOrFail();
        $asOfDate = ($asOf ?? now())->toDateString();

        $rate = ExchangeRate::query()
            ->where('currency_id', $currency->id)
            ->where('effective_date', '<=', $asOfDate)
            ->orderByDesc('effective_date')
            ->first();

        if ($rate === null) {
            throw new InvalidArgumentException("No exchange rate found for {$fromCurrency} on or before {$asOfDate}.");
        }

        return $rate;
    }

    /**
     * @return array{gain_loss: float, posted: bool}
     */
    public function postRealizedFxGainLoss(
        int $hotelId,
        float $originalAmount,
        string $originalCurrency,
        int $originalExchangeRateId,
        float $settlementAmountIdr,
        CarbonInterface $settlementDate,
        string $sourceType,
        int $sourceId,
        string $referenceNumber,
    ): array {
        if ($originalCurrency === $this->baseCurrencyCode()) {
            return ['gain_loss' => 0.0, 'posted' => false];
        }

        $originalRate = ExchangeRate::query()->findOrFail($originalExchangeRateId);
        $originalIdr = round($originalAmount * (float) $originalRate->rate_to_base, 2);
        $gainLoss = round($settlementAmountIdr - $originalIdr, 2);

        if (abs($gainLoss) < 0.01) {
            return ['gain_loss' => 0.0, 'posted' => false];
        }

        if ($gainLoss > 0) {
            $gainAccount = $this->glPostingService->findAccountByCode($hotelId, '4-9200');
            $offsetAccount = $this->glPostingService->findAccountByCode($hotelId, '1-1200');
            $lines = [
                [
                    'hotel_id' => $hotelId,
                    'chart_of_account_id' => $offsetAccount->id,
                    'transaction_date' => $settlementDate->toDateString(),
                    'debit' => $gainLoss,
                    'credit' => 0,
                    'description' => "Realized FX gain on {$referenceNumber}",
                    'reference_number' => $referenceNumber,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
                [
                    'hotel_id' => $hotelId,
                    'chart_of_account_id' => $gainAccount->id,
                    'transaction_date' => $settlementDate->toDateString(),
                    'debit' => 0,
                    'credit' => $gainLoss,
                    'description' => "Realized FX gain on {$referenceNumber}",
                    'reference_number' => $referenceNumber,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
            ];
        } else {
            $lossAmount = abs($gainLoss);
            $lossAccount = $this->glPostingService->findAccountByCode($hotelId, '6-9100');
            $offsetAccount = $this->glPostingService->findAccountByCode($hotelId, '1-1200');
            $lines = [
                [
                    'hotel_id' => $hotelId,
                    'chart_of_account_id' => $lossAccount->id,
                    'transaction_date' => $settlementDate->toDateString(),
                    'debit' => $lossAmount,
                    'credit' => 0,
                    'description' => "Realized FX loss on {$referenceNumber}",
                    'reference_number' => $referenceNumber,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
                [
                    'hotel_id' => $hotelId,
                    'chart_of_account_id' => $offsetAccount->id,
                    'transaction_date' => $settlementDate->toDateString(),
                    'debit' => 0,
                    'credit' => $lossAmount,
                    'description' => "Realized FX loss on {$referenceNumber}",
                    'reference_number' => $referenceNumber,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
            ];
        }

        $this->glPostingService->post($lines);

        return ['gain_loss' => $gainLoss, 'posted' => true];
    }

    public function baseCurrencyCode(): string
    {
        $base = Currency::query()->where('code', 'IDR')->first();

        return $base?->code ?? 'IDR';
    }
}
