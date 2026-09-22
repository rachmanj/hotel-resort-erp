<?php

namespace App\Actions\Billing;

use App\Enums\FolioItemType;
use App\Enums\GuestInvoiceStatus;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\GuestInvoice;
use App\Models\GuestInvoiceLine;
use App\Services\GuestInvoiceNumberService;
use Illuminate\Support\Facades\DB;

class SyncGuestInvoiceAction
{
    public function __construct(
        private GuestInvoiceNumberService $numberService,
    ) {}

    /**
     * Keep the guest invoice in step with the charges on the folio.
     *
     * A draft is rewritten in place so front office always sees the current charges;
     * a released document is never touched, a new revision is issued instead so the
     * copy already handed to the guest stays intact.
     */
    public function __invoke(Folio $folio): GuestInvoice
    {
        return DB::transaction(function () use ($folio): GuestInvoice {
            $current = GuestInvoice::query()
                ->withoutGlobalScope('hotel')
                ->where('folio_id', $folio->id)
                ->orderByDesc('revision')
                ->lockForUpdate()
                ->first();

            $lines = $this->buildLines($folio);

            if ($current === null) {
                return $this->issue($folio, $lines, revision: 1);
            }

            if ($current->isDraft()) {
                $this->replaceLines($current, $lines);

                return $current;
            }

            if ($this->signature($lines) === $this->signatureOf($current)) {
                return $current;
            }

            return $this->issue($folio, $lines, revision: $current->revision + 1);
        });
    }

    /**
     * Folios opened before this module existed have no document yet, so the latest
     * revision is created on demand when someone opens the invoice.
     */
    public function current(Folio $folio): GuestInvoice
    {
        $invoice = GuestInvoice::query()
            ->withoutGlobalScope('hotel')
            ->where('folio_id', $folio->id)
            ->orderByDesc('revision')
            ->first();

        return $invoice ?? $this($folio);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function issue(Folio $folio, array $lines, int $revision): GuestInvoice
    {
        $reserved = $this->numberService->reserveNext($folio->hotel_id, now());

        $invoice = GuestInvoice::query()->create([
            'hotel_id' => $folio->hotel_id,
            'folio_id' => $folio->id,
            'reservation_id' => $folio->reservation_id,
            'sequence' => $reserved['sequence'],
            'year' => $reserved['year'],
            'month' => $reserved['month'],
            'number' => $reserved['number'],
            'status' => GuestInvoiceStatus::Draft->value,
            'revision' => $revision,
            'prepared_by' => $this->resolvePreparedBy($folio),
            'subtotal' => 0,
            'total' => 0,
        ]);

        $this->replaceLines($invoice, $lines);

        return $invoice;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function replaceLines(GuestInvoice $invoice, array $lines): void
    {
        GuestInvoiceLine::query()->where('guest_invoice_id', $invoice->id)->delete();

        foreach ($lines as $index => $line) {
            GuestInvoiceLine::query()->create([
                'guest_invoice_id' => $invoice->id,
                'sort_order' => $index + 1,
                ...$line,
            ]);
        }

        $invoice->update([
            'subtotal' => round(array_sum(array_column($lines, 'amount')), 2),
            'total' => round(array_sum(array_column($lines, 'line_total')), 2),
        ]);

        $invoice->load('lines');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildLines(Folio $folio): array
    {
        return $folio->items()
            ->orderBy('posted_at')
            ->orderBy('id')
            ->get()
            ->map(function (FolioItem $item): array {
                $nights = $this->nightsOf($item);

                return [
                    'folio_item_id' => $item->id,
                    'description' => $item->description,
                    'quantity' => $nights === null ? (float) $item->quantity : 1.0,
                    'nights' => $nights,
                    'unit_price' => (float) $item->unit_price,
                    'amount' => (float) $item->amount,
                    'tax_amount' => (float) $item->tax_amount,
                    'service_charge_amount' => (float) $item->service_charge_amount,
                    'line_total' => round($item->line_total, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Room charges are posted one line per room with quantity holding the number of
     * nights, which is the Ns column on the printed invoice. Every other charge is
     * billed per unit and leaves Ns blank.
     */
    private function nightsOf(FolioItem $item): ?int
    {
        if ($item->item_type !== FolioItemType::Room) {
            return null;
        }

        return max(1, (int) $item->quantity);
    }

    private function resolvePreparedBy(Folio $folio): ?int
    {
        return $folio->items()->orderBy('id')->value('posted_by')
            ?? auth()->id();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function signature(array $lines): string
    {
        return collect($lines)
            ->map(fn (array $line) => sprintf(
                '%s|%.2f|%s|%.2f|%.2f|%.2f|%.2f',
                $line['description'],
                $line['quantity'],
                $line['nights'] ?? '-',
                $line['unit_price'],
                $line['amount'],
                $line['tax_amount'],
                $line['service_charge_amount'],
            ))
            ->implode(';');
    }

    private function signatureOf(GuestInvoice $invoice): string
    {
        return $this->signature(
            $invoice->lines()
                ->get()
                ->map(fn (GuestInvoiceLine $line) => [
                    'description' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'nights' => $line->nights,
                    'unit_price' => (float) $line->unit_price,
                    'amount' => (float) $line->amount,
                    'tax_amount' => (float) $line->tax_amount,
                    'service_charge_amount' => (float) $line->service_charge_amount,
                ])
                ->all(),
        );
    }
}
