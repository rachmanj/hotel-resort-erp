<?php

namespace App\Console\Commands;

use App\Enums\FolioItemType;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Services\TaxCalculator;
use App\Support\FolioItemAppliesTo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-splits room and F&B folio items posted under the old on-top tax model.
 *
 * The stored amount has always been the price list price, so the correction never
 * touches amount or unit_price: it only fills service_charge_amount and tax_amount
 * with the inclusive split and flags the row, which drops the line total from
 * amount x 1.21 back to the price the guest was quoted.
 *
 * Existing general ledger rows are left untouched; the overstated revenue and tax
 * they carry needs a reversing journal entry from Finance, not a silent rewrite.
 */
class ResplitInclusiveTaxCommand extends Command
{
    protected $signature = 'billing:resplit-inclusive-tax
                            {--dry-run : Report the changes without writing them}
                            {--folio= : Limit to a single folio id or folio number}';

    protected $description = 'Re-split room and F&B folio items as tax inclusive (service charge + PBJT carved out of the price)';

    public function handle(TaxCalculator $taxCalculator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $folioFilter = $this->option('folio');

        $query = FolioItem::query()
            ->whereIn('item_type', [FolioItemType::Room->value, FolioItemType::Fb->value])
            ->where('is_tax_inclusive', false)
            ->orderBy('id');

        if ($folioFilter !== null) {
            $folioId = Folio::query()
                ->withoutGlobalScope('hotel')
                ->where('folio_no', $folioFilter)
                ->when(ctype_digit((string) $folioFilter), fn ($q) => $q->orWhere('id', (int) $folioFilter))
                ->value('id');

            if ($folioId === null) {
                $this->error("Folio [{$folioFilter}] not found.");

                return self::FAILURE;
            }

            $query->where('folio_id', $folioId);
        }

        $items = $query->get();

        if ($items->isEmpty()) {
            $this->info('Nothing to re-split. Every room and F&B folio item is already tax inclusive.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        $converted = 0;

        foreach ($items as $item) {
            $amount = round((float) $item->amount, 2);
            $split = $taxCalculator->extractInclusive(
                $amount,
                FolioItemAppliesTo::forItemType($item->item_type->value),
            );

            $this->line(sprintf(
                'Item #%d %s',
                $item->id,
                $item->description,
            ));
            $this->line(sprintf(
                '  before: amount %s · SC %s · tax %s · line total %s',
                $this->money($amount),
                $this->money((float) $item->service_charge_amount),
                $this->money((float) $item->tax_amount),
                $this->money($item->line_total),
            ));
            $this->line(sprintf(
                '  after:  amount %s · DPP %s · SC %s · PBJT %s · line total %s',
                $this->money($amount),
                $this->money($split['dpp']),
                $this->money($split['service_charge']),
                $this->money($split['tax']),
                $this->money($amount),
            ));

            if (! $dryRun) {
                DB::table('folio_items')
                    ->where('id', $item->id)
                    ->update([
                        'service_charge_amount' => $split['service_charge'],
                        'tax_amount' => $split['tax'],
                        'is_tax_inclusive' => true,
                        'updated_at' => now(),
                    ]);
            }

            $converted++;
        }

        $this->newLine();
        $this->info($dryRun
            ? "{$converted} folio item(s) would be re-split."
            : "{$converted} folio item(s) re-split as tax inclusive.");

        return self::SUCCESS;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }
}
