<?php

namespace App\Services;

use App\Enums\FolioItemType;
use App\Models\FolioItem;
use App\Models\TaxRule;
use App\Support\FolioItemAppliesTo;
use App\Support\TaxAmountCalculator;
use Illuminate\Support\Collection;

class TaxCalculator
{
    /**
     * @return array{subtotal: float, service_charge: float, tax: float, total: float}
     */
    public function calculate(float $amount, string $appliesTo = 'room'): array
    {
        return TaxAmountCalculator::calculate($amount, $this->activeRulesPayload($appliesTo));
    }

    /**
     * @return array<int, array{code: string, rate_percent: float, is_compounding: bool}>
     */
    public function activeRulesPayload(string $appliesTo): array
    {
        return $this->getActiveRules($appliesTo)
            ->map(fn (TaxRule $rule) => [
                'code' => $rule->code,
                'rate_percent' => (float) $rule->rate_percent,
                'is_compounding' => (bool) $rule->is_compounding,
            ])
            ->values()
            ->all();
    }

    /**
     * Rules payload for previewing a folio item entry form. Returns an empty
     * array for non-taxable item types (everything except room and F&B) so
     * previews never show a tax/service charge breakdown that postCharge()
     * will not actually apply.
     *
     * @return array<int, array{code: string, rate_percent: float, is_compounding: bool}>
     */
    public function activeRulesPayloadForItemType(string $itemType): array
    {
        if (! FolioItemType::from($itemType)->isTaxable()) {
            return [];
        }

        return $this->activeRulesPayload(FolioItemAppliesTo::forItemType($itemType));
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
