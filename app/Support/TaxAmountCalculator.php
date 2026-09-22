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
        return self::forward($amount, $rules);
    }

    /**
     * Split a price that already includes service charge and tax into its parts.
     *
     * The DPP is derived by dividing the inclusive price by the gross-up factor of
     * the active rules, then the same forward arithmetic as calculate() produces the
     * service charge and tax. Each part is rounded independently, so the three parts
     * can differ from the inclusive price by a cent; the guest-facing total is always
     * the untouched inclusive price, never the sum of the parts.
     *
     * @param  array<int, array{code: string, rate_percent: float|int|string, is_compounding: bool}>  $rules
     * @return array{dpp: float, service_charge: float, tax: float, total: float}
     */
    public static function extractInclusive(float $amount, array $rules): array
    {
        $total = round($amount, 2);
        $dpp = round($total / self::inclusiveFactor($rules), 2);
        $forward = self::forward($dpp, $rules);

        return [
            'dpp' => $dpp,
            'service_charge' => $forward['service_charge'],
            'tax' => $forward['tax'],
            'total' => $total,
        ];
    }

    /**
     * Multiplier that turns a DPP into the inclusive price, mirroring the rule order
     * used by forward().
     *
     * @param  array<int, array{code: string, rate_percent: float|int|string, is_compounding: bool}>  $rules
     */
    public static function inclusiveFactor(array $rules): float
    {
        $serviceChargeFactor = 0.0;
        $taxFactor = 0.0;
        $runningFactor = 1.0;

        foreach ($rules as $rule) {
            $rate = (float) $rule['rate_percent'] / 100;

            if ($rule['code'] === self::SERVICE_CHARGE_CODE) {
                $serviceChargeFactor = $rate;
                $runningFactor = 1 + $rate;
            } elseif ($rule['is_compounding']) {
                $taxFactor = $runningFactor * $rate;
            } else {
                $taxFactor += $rate;
            }
        }

        return 1 + $serviceChargeFactor + $taxFactor;
    }

    /**
     * @param  array<int, array{code: string, rate_percent: float|int|string, is_compounding: bool}>  $rules
     * @return array{subtotal: float, service_charge: float, tax: float, total: float}
     */
    private static function forward(float $amount, array $rules): array
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
