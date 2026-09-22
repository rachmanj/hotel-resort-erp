<?php

namespace App\Actions\Reservations;

use App\Enums\ProformaInvoiceStatus;
use App\Models\ProformaInvoice;
use App\Models\ProformaInvoiceLine;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Services\ProformaInvoiceNumberService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncProformaInvoiceAction
{
    public function __construct(
        private ProformaInvoiceNumberService $numberService,
        private RefreshProformaInvoiceTotalsAction $refreshTotals,
    ) {}

    /**
     * Keep the reservation proforma invoice in step with the reservation.
     *
     * A draft is rewritten in place; a released document is never touched, a new
     * revision is issued instead so the copy already sent to the customer stays intact.
     */
    public function __invoke(Reservation $reservation): ProformaInvoice
    {
        return DB::transaction(function () use ($reservation): ProformaInvoice {
            $current = ProformaInvoice::query()
                ->withoutGlobalScope('hotel')
                ->where('reservation_id', $reservation->id)
                ->orderByDesc('revision')
                ->lockForUpdate()
                ->first();

            $lines = $this->buildLines($reservation);

            if ($current === null) {
                return $this->issue($reservation, $lines, revision: 1);
            }

            if ($current->isDraft()) {
                $this->replaceLines($current, $lines);

                return $current;
            }

            if ($this->signature($lines) === $this->signatureOf($current)) {
                return $current;
            }

            return $this->issue($reservation, $lines, revision: $current->revision + 1);
        });
    }

    /**
     * Reservations booked before this module existed have no document yet, so the
     * latest revision is created on demand.
     */
    public function current(Reservation $reservation): ProformaInvoice
    {
        $invoice = ProformaInvoice::query()
            ->where('reservation_id', $reservation->id)
            ->orderByDesc('revision')
            ->first();

        return $invoice ?? $this($reservation);
    }

    /**
     * @param  list<array{description: string, note: string|null, quantity: int, nights: int, unit_price: float, amount: float}>  $lines
     */
    private function issue(Reservation $reservation, array $lines, int $revision): ProformaInvoice
    {
        $reserved = $this->numberService->reserveNext($reservation->hotel_id, now());

        $invoice = ProformaInvoice::query()->create([
            'hotel_id' => $reservation->hotel_id,
            'reservation_id' => $reservation->id,
            'sequence' => $reserved['sequence'],
            'year' => $reserved['year'],
            'number' => $reserved['number'],
            'status' => ProformaInvoiceStatus::Draft->value,
            'revision' => $revision,
            'subtotal' => 0,
            'total' => 0,
            'prepared_by' => $reservation->createdBy?->name ?? config('proforma.prepared_by'),
        ]);

        $this->replaceLines($invoice, $lines);

        return $invoice;
    }

    /**
     * @param  list<array{description: string, note: string|null, quantity: int, nights: int, unit_price: float, amount: float}>  $lines
     */
    private function replaceLines(ProformaInvoice $invoice, array $lines): void
    {
        ProformaInvoiceLine::query()->where('proforma_invoice_id', $invoice->id)->delete();

        foreach ($lines as $index => $line) {
            ProformaInvoiceLine::query()->create([
                'proforma_invoice_id' => $invoice->id,
                'sort_order' => $index + 1,
                ...$line,
            ]);
        }

        $total = array_sum(array_column($lines, 'amount'));

        $invoice->update([
            'subtotal' => round($total, 2),
            'total' => round($total, 2),
        ]);

        ($this->refreshTotals)($invoice);

        $invoice->load('lines');
    }

    /**
     * @return list<array{description: string, note: string|null, quantity: int, nights: int, unit_price: float, amount: float}>
     */
    private function buildLines(Reservation $reservation): array
    {
        $nights = max(1, (int) $reservation->arrival_date->diffInDays($reservation->departure_date));

        $rooms = $reservation->reservationRooms()->with('roomType')->get();

        return $rooms
            ->groupBy(fn (ReservationRoom $room) => $room->room_type_id.':'.(string) $room->nightly_rate)
            ->map(function (Collection $group) use ($nights): array {
                /** @var ReservationRoom $first */
                $first = $group->first();
                $quantity = $group->count();
                $unitPrice = (float) $first->nightly_rate;

                return [
                    'description' => $first->roomType?->name ?? 'Room',
                    'note' => config('proforma.room_line_note'),
                    'quantity' => $quantity,
                    'nights' => $nights,
                    'unit_price' => $unitPrice,
                    'amount' => round($unitPrice * $quantity * $nights, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<array{description: string, note: string|null, quantity: int, nights: int, unit_price: float, amount: float}>  $lines
     */
    private function signature(array $lines): string
    {
        return collect($lines)
            ->map(fn (array $line) => sprintf(
                '%s|%d|%d|%.2f',
                $line['description'],
                $line['quantity'],
                $line['nights'],
                $line['unit_price'],
            ))
            ->implode(';');
    }

    private function signatureOf(ProformaInvoice $invoice): string
    {
        return $this->signature(
            $invoice->lines()
                ->get()
                ->map(fn (ProformaInvoiceLine $line) => [
                    'description' => $line->description,
                    'note' => $line->note,
                    'quantity' => $line->quantity,
                    'nights' => $line->nights,
                    'unit_price' => (float) $line->unit_price,
                    'amount' => (float) $line->amount,
                ])
                ->all(),
        );
    }
}
