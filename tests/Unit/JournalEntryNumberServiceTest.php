<?php

namespace Tests\Unit;

use App\Models\Hotel;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\JournalEntryNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JournalEntryNumberServiceTest extends TestCase
{
    use RefreshDatabase;

    private JournalEntryNumberService $service;

    private Hotel $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(JournalEntryNumberService::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'JV Number Hotel',
            'code' => 'JVH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $this->hotel->id]);
    }

    public function test_first_number_uses_jv_yyyymm_0001_format(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');

        $number = $this->service->nextNumber();

        $this->assertSame('JV-202609-0001', $number);

        Carbon::setTestNow();
    }

    public function test_consecutive_calls_increment_sequence_within_same_month(): void
    {
        Carbon::setTestNow('2026-09-15 12:00:00');

        $user = User::factory()->create(['hotel_id' => $this->hotel->id]);

        $first = $this->service->nextNumber();

        JournalEntry::query()->create([
            'hotel_id' => $this->hotel->id,
            'journal_no' => $first,
            'entry_date' => '2026-09-15',
            'description' => 'First reserved number',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $second = $this->service->nextNumber();

        $this->assertSame('JV-202609-0001', $first);
        $this->assertSame('JV-202609-0002', $second);
        $this->assertNotSame($first, $second);

        Carbon::setTestNow();
    }

    public function test_sequence_continues_from_existing_journal_entries(): void
    {
        Carbon::setTestNow('2026-09-20 09:00:00');

        $user = User::factory()->create(['hotel_id' => $this->hotel->id]);

        JournalEntry::query()->create([
            'hotel_id' => $this->hotel->id,
            'journal_no' => 'JV-202609-0005',
            'entry_date' => '2026-09-01',
            'description' => 'Seeded',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $next = $this->service->nextNumber();

        $this->assertSame('JV-202609-0006', $next);

        Carbon::setTestNow();
    }
}
