<?php

namespace App\Services;

use App\Models\ProformaInvoice;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProformaInvoiceNumberService
{
    /**
     * @var array<int, string>
     */
    private const ROMAN_MONTHS = [
        1 => 'I',
        2 => 'II',
        3 => 'III',
        4 => 'IV',
        5 => 'V',
        6 => 'VI',
        7 => 'VII',
        8 => 'VIII',
        9 => 'IX',
        10 => 'X',
        11 => 'XI',
        12 => 'XII',
    ];

    /**
     * Reserve the next document number for a hotel and year, e.g. 066/PI/PRATA/VIII/2026.
     *
     * Must be called inside the same transaction that inserts the proforma invoice row:
     * the range lock taken here is only held until that transaction commits, which is what
     * keeps two concurrent reservations from claiming the same sequence.
     *
     * @return array{sequence: int, year: int, number: string}
     */
    public function reserveNext(int $hotelId, CarbonInterface $issuedOn): array
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Proforma invoice numbers must be reserved inside a database transaction.');
        }

        $year = $issuedOn->year;

        $lastSequence = (int) ProformaInvoice::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotelId)
            ->where('year', $year)
            ->lockForUpdate()
            ->max('sequence');

        $sequence = $lastSequence + 1;

        return [
            'sequence' => $sequence,
            'year' => $year,
            'number' => $this->format($sequence, $issuedOn),
        ];
    }

    public function format(int $sequence, CarbonInterface $issuedOn): string
    {
        return sprintf(
            '%s/PI/%s/%s/%d',
            str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            config('proforma.number_prefix'),
            self::ROMAN_MONTHS[$issuedOn->month],
            $issuedOn->year,
        );
    }
}
