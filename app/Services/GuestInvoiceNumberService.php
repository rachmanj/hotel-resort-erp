<?php

namespace App\Services;

use App\Models\GuestInvoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GuestInvoiceNumberService
{
    /**
     * Reserve the next guest invoice number for a hotel, e.g. 2607003 — year 26, month 07, running number 003.
     *
     * The running number restarts every month, so the counter is keyed on year + month.
     * Must be called inside the same transaction that inserts the guest invoice row: the
     * range lock taken here is only held until that transaction commits, which is what keeps
     * two folios billed at the same moment from claiming the same sequence. The
     * (hotel_id, year, month, sequence) unique index is the last line of defence.
     *
     * @return array{sequence: int, year: int, month: int, number: string}
     */
    public function reserveNext(int $hotelId, CarbonInterface $issuedOn): array
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Guest invoice numbers must be reserved inside a database transaction.');
        }

        $year = $issuedOn->year;
        $month = $issuedOn->month;

        $lastSequence = (int) GuestInvoice::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotelId)
            ->where('year', $year)
            ->where('month', $month)
            ->lockForUpdate()
            ->max('sequence');

        $sequence = $lastSequence + 1;

        return [
            'sequence' => $sequence,
            'year' => $year,
            'month' => $month,
            'number' => $this->format($sequence, $year, $month),
        ];
    }

    public function format(int $sequence, int $year, int $month): string
    {
        return sprintf('%02d%02d%03d', $year % 100, $month, $sequence);
    }
}
