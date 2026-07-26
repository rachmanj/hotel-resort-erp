<?php

namespace App\Actions\Currency;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;

class StoreExchangeRate
{
    public function __invoke(Currency $currency, float $rate, string $effectiveDate): ExchangeRate
    {
        $exchangeRate = ExchangeRate::query()->updateOrCreate(
            [
                'currency_id' => $currency->id,
                'effective_date' => $effectiveDate,
            ],
            [
                'rate_to_base' => $rate,
            ]
        );

        $currency->update([
            'exchange_rate_to_base' => $rate,
            'effective_date' => $effectiveDate,
        ]);

        Cache::forget('currencies.active');

        return $exchangeRate;
    }
}
