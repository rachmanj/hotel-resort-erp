<?php

namespace Tests\Unit;

use App\Enums\ProformaInvoiceStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\ProformaInvoice;
use App\Models\Reservation;
use App\Services\ProformaInvoiceNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProformaInvoiceNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private ProformaInvoiceNumberService $numberService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->numberService = app(ProformaInvoiceNumberService::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Pratasaba Resort',
            'code' => 'PRT',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);
    }

    public function test_2026_first_number_starts_after_configured_floor_when_no_existing_documents(): void
    {
        Carbon::setTestNow('2026-09-10 10:00:00');

        $first = $this->reserveNext();

        $this->assertSame(72, $first['sequence']);
        $this->assertSame(2026, $first['year']);
        $this->assertSame('072/PI/PRATA/IX/2026', $first['number']);

        $this->seedProformaInvoice($first['sequence'], $first['number']);

        $second = $this->reserveNext();

        $this->assertSame(73, $second['sequence']);
        $this->assertSame('073/PI/PRATA/IX/2026', $second['number']);

        Carbon::setTestNow();
    }

    public function test_year_without_configured_floor_is_unaffected(): void
    {
        Carbon::setTestNow('2027-02-01 10:00:00');

        $reserved = $this->reserveNext();

        $this->assertSame(1, $reserved['sequence']);
        $this->assertSame(2027, $reserved['year']);
        $this->assertSame('001/PI/PRATA/II/2027', $reserved['number']);

        Carbon::setTestNow();
    }

    public function test_existing_sequence_above_floor_continues_from_database_maximum(): void
    {
        Carbon::setTestNow('2026-09-10 10:00:00');

        $this->seedProformaInvoice(sequence: 95, number: '095/PI/PRATA/IX/2026');

        $reserved = $this->reserveNext();

        $this->assertSame(96, $reserved['sequence']);
        $this->assertSame('096/PI/PRATA/IX/2026', $reserved['number']);

        Carbon::setTestNow();
    }

    /**
     * @return array{sequence: int, year: int, number: string}
     */
    private function reserveNext(): array
    {
        return DB::transaction(
            fn (): array => $this->numberService->reserveNext($this->hotel->id, now()),
        );
    }

    private function seedProformaInvoice(int $sequence, string $number): void
    {
        $guest = Guest::query()->create([
            'full_name' => 'Numbering Guest',
            'phone' => '0812'.random_int(10000000, 99999999),
        ]);

        $reservation = Reservation::withoutEvents(fn () => Reservation::query()->create([
            'hotel_id' => $this->hotel->id,
            'reservation_code' => 'RSV-NUM-'.random_int(1000, 9999),
            'guest_id' => $guest->id,
            'source' => ReservationSource::Walkin->value,
            'status' => ReservationStatus::Confirmed->value,
            'arrival_date' => '2026-09-01',
            'departure_date' => '2026-09-03',
            'adults' => 2,
            'children' => 0,
        ]));

        ProformaInvoice::query()->create([
            'hotel_id' => $this->hotel->id,
            'reservation_id' => $reservation->id,
            'sequence' => $sequence,
            'year' => 2026,
            'number' => $number,
            'status' => ProformaInvoiceStatus::Draft->value,
            'revision' => 1,
            'subtotal' => 0,
            'total' => 0,
        ]);
    }
}
