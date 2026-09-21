<?php

namespace App\Support;

/**
 * Shared tax line arithmetic for folio charges.
 *
 * Rules are applied in array order:
 * - service_charge: computed on subtotal, updates running base to subtotal + service charge
 * - is_compounding tax: computed on running base (subtotal + service charge after SC rule)
 * - non-compounding tax: computed on subtotal only
 */
class TaxAmountCalculator
{
    public const SERVICE_CHARGE_CODE = 'service_charge';

    /**
     * @param  array<int, array{code: string, rate_percent: float|int|string, is_compounding: bool}>  $rules
     * @return array{subtotal: float, service_charge: float, tax: float, total: float}
     */
    public static function calculate(float $amount, array $rules): array
    {
        $subtotal = round($amount, 2);
        $serviceCharge = 0.0;
        $tax = 0.0;
        $runningBase = $subtotal;

        foreach ($rules as $rule) {
            $rate = (float) $rule['rate_percent'] / 100;

            if ($rule['code'] === self::SERVICE_CHARGE_CODE) {
                $serviceCharge = round($subtotal * $rate, 2);
                $runningBase = $subtotal + $serviceCharge;
            } elseif ($rule['is_compounding']) {
                $tax = round($runningBase * $rate, 2);
            } else {
                $tax += round($subtotal * $rate, 2);
            }
        }

        return [
            'subtotal' => $subtotal,
            'service_charge' => $serviceCharge,
            'tax' => $tax,
            'total' => round($subtotal + $serviceCharge + $tax, 2),
        ];
    }
}
