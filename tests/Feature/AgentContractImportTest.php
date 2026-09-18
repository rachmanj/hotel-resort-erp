<?php

namespace Tests\Feature;

use App\Enums\AgentRateCategory;
use App\Models\Agent;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Services\AgentRateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentContractImportTest extends TestCase
{
    use RefreshDatabase;

    private const CSV_PATH = 'database/data/pratasaba_agents_2026.csv';

    private Hotel $hotel;

    private RoomType $suiteRoomType;

    private RoomType $deluxeRoomType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::query()->create([
            'name' => 'Pratasaba Resort',
            'code' => 'PRS',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $this->suiteRoomType = RoomType::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Suite King Seaview',
            'code' => 'STKS',
            'max_occupancy' => 4,
            'base_rate' => 2590000,
            'is_active' => true,
        ]);

        $this->deluxeRoomType = RoomType::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe Twin Seaview',
            'code' => 'DLTS',
            'max_occupancy' => 2,
            'base_rate' => 1590000,
            'is_active' => true,
        ]);

        foreach (['DLTG', 'DLKS', 'DLKS 06', 'DLKG', 'GDKS', 'GDTS', 'STTS'] as $code) {
            RoomType::query()->create([
                'hotel_id' => $this->hotel->id,
                'name' => $code,
                'code' => $code,
                'max_occupancy' => 2,
                'base_rate' => 1500000,
                'is_active' => true,
            ]);
        }
    }

    public function test_imports_agents_and_tier_rates_from_contract_csv(): void
    {
        $expectedAgentCount = $this->countUniqueCompaniesInCsv();

        $this->artisan('agents:import-contract')
            ->assertSuccessful();

        $this->assertSame($expectedAgentCount, Agent::query()->withoutGlobalScope('hotel')->count());

        $tierAAgent = Agent::query()
            ->withoutGlobalScope('hotel')
            ->where('name', 'Travydoor Tour')
            ->first();

        $this->assertNotNull($tierAAgent);
        $this->assertSame(AgentRateCategory::A, $tierAAgent->rate_category);

        $transBorneoCount = Agent::query()
            ->withoutGlobalScope('hotel')
            ->where('name', 'Trans Borneo')
            ->count();

        $this->assertSame(1, $transBorneoCount);

        $this->artisan('agents:import-contract')
            ->assertSuccessful();

        $this->assertSame($expectedAgentCount, Agent::query()->withoutGlobalScope('hotel')->count());

        $tierAAgent = Agent::query()
            ->withoutGlobalScope('hotel')
            ->where('name', 'Travydoor Tour')
            ->firstOrFail();

        $tierCAgent = Agent::query()
            ->withoutGlobalScope('hotel')
            ->where('name', 'Escape Indonesia')
            ->firstOrFail();

        $this->assertSame(AgentRateCategory::C, $tierCAgent->rate_category);

        $checkin = Carbon::parse('2026-06-01');
        $checkout = Carbon::parse('2026-06-03');
        $rateService = app(AgentRateService::class);

        $suiteRate = $rateService->resolveNightlyRate(
            $tierAAgent,
            $this->suiteRoomType->id,
            $checkin,
            $checkout,
        );

        $deluxeRate = $rateService->resolveNightlyRate(
            $tierAAgent,
            $this->deluxeRoomType->id,
            $checkin,
            $checkout,
        );

        $this->assertSame('2100000.00', $suiteRate);
        $this->assertSame('1300000.00', $deluxeRate);
    }

    private function countUniqueCompaniesInCsv(): int
    {
        $rows = array_map('str_getcsv', file(base_path(self::CSV_PATH)));
        array_shift($rows);

        $companies = [];

        foreach ($rows as $row) {
            $company = trim((string) ($row[0] ?? ''));

            if ($company !== '') {
                $companies[$company] = true;
            }
        }

        return count($companies);
    }
}
