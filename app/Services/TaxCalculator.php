<?php

namespace App\Services;

use App\Models\FolioItem;
use App\Models\TaxRule;
use Illuminate\Support\Collection;

class TaxCalculator
{
    /**
     * @return array{subtotal: float, service_charge: float, tax: float, total: float}
     */
    public function calculate(float $amount, string $appliesTo = 'room'): array
    {
        $rules = $this->getActiveRules($appliesTo);

        $subtotal = round($amount, 2);
        $serviceCharge = 0.0;
        $tax = 0.0;
        $runningBase = $subtotal;

        foreach ($rules as $rule) {
            $rate = (float) $rule->rate_percent / 100;

            if ($rule->code === 'service_charge') {
                $serviceCharge = round($subtotal * $rate, 2);
                $runningBase = $subtotal + $serviceCharge;
            } elseif ($rule->is_compounding) {
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

    /**
     * @param  Collection<int, FolioItem>  $items
     * @return array{subtotal: float, service_charge: float, tax: float, total: float}
     */
    public function applyToFolio(Collection $items, string $appliesTo): array
    {
        $subtotal = $items
            ->whereNotIn('item_type', ['tax', 'service_charge', 'discount', 'deposit_credit'])
            ->sum(fn ($item) => (float) $item->amount);

        return $this->calculate($subtotal, $appliesTo);
    }

    /**
     * @return Collection<int, TaxRule>
     */
    private function getActiveRules(string $appliesTo): Collection
    {
        return TaxRule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($appliesTo): void {
                $query->where('applies_to', 'all')
                    ->orWhere('applies_to', $appliesTo);
            })
            ->orderBy('order')
            ->get();
    }
}
