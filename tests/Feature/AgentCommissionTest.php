<?php

namespace Tests\Feature;

use App\Enums\AgentCommissionStatus;
use App\Enums\AgentType;
use App\Enums\CommissionBasis;
use App\Enums\FolioItemType;
use App\Enums\FolioStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\Floor;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\GeneralLedger;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\AgentCommissionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgentCommissionTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private Agent $agent;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Test Hotel',
            'code' => 'TST',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $this->seed(ChartOfAccountsSeeder::class);

        $roomType = RoomType::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe',
            'code' => 'DLX',
            'max_occupancy' => 2,
            'base_rate' => 1000000,
            'is_active' => true,
        ]);

        $floor = Floor::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Floor 1',
            'level' => 1,
        ]);

        $room = Room::query()->create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'number' => '101',
            'status' => RoomStatus::VacantClean->value,
        ]);

        $guest = Guest::query()->create(['full_name' => 'Agent Guest']);

        $this->agent = Agent::query()->create([
            'hotel_id' => $this->hotel->id,
            'agent_type' => AgentType::Travel->value,
            'name' => 'Travel Agent Co',
            'code' => 'TAC',
            'commission_percent' => 10,
            'commission_basis' => CommissionBasis::NetRoom->value,
            'is_active' => true,
        ]);

        $this->reservation = Reservation::query()->create([
            'hotel_id' => $this->hotel->id,
            'reservation_code' => 'RES-TEST-0001',
            'guest_id' => $guest->id,
            'agent_id' => $this->agent->id,
            'source' => 'phone',
            'status' => ReservationStatus::CheckedOut->value,
            'arrival_date' => now()->subDays(2)->toDateString(),
            'departure_date' => now()->toDateString(),
            'adults' => 1,
            'children' => 0,
        ]);

        ReservationRoom::query()->create([
            'reservation_id' => $this->reservation->id,
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'nightly_rate' => 1000000,
            'status' => 'checked_out',
        ]);

        $folio = Folio::query()->create([
            'hotel_id' => $this->hotel->id,
            'folio_no' => 'FOL-TEST-0001',
            'reservation_id' => $this->reservation->id,
            'guest_id' => $guest->id,
            'type' => 'master',
            'status' => FolioStatus::Closed->value,
            'opened_at' => now()->subDays(2),
            'closed_at' => now(),
        ]);

        FolioItem::query()->create([
            'folio_id' => $folio->id,
            'item_type' => FolioItemType::Room->value,
            'description' => 'Room charge',
            'quantity' => 2,
            'unit_price' => 1000000,
            'amount' => 2000000,
            'tax_amount' => 220000,
            'service_charge_amount' => 200000,
            'posted_at' => now(),
        ]);
    }

    public function test_commission_calculates_net_room_basis(): void
    {
        session(['current_hotel_id' => $this->hotel->id]);

        $service = app(AgentCommissionService::class);
        $result = $service->calculateForReservation($this->reservation, $this->agent);

        $this->assertEquals(2000000, $result['base_amount']);
        $this->assertEquals(200000, $result['commission_amount']);
    }

    public function test_commission_accrual_posts_balanced_gl(): void
    {
        session(['current_hotel_id' => $this->hotel->id]);

        $user = User::factory()->create();
        $service = app(AgentCommissionService::class);

        $commission = $service->accrue($this->reservation, $this->agent, $user);

        $this->assertDatabaseHas('agent_commissions', [
            'id' => $commission->id,
            'agent_id' => $this->agent->id,
            'reservation_id' => $this->reservation->id,
            'status' => AgentCommissionStatus::Pending->value,
            'commission_amount' => 200000,
        ]);

        $glRows = GeneralLedger::query()
            ->where('source_type', AgentCommission::class)
            ->where('source_id', $commission->id)
            ->get();

        $this->assertCount(2, $glRows);
        $this->assertEquals(200000, $glRows->sum('debit'));
        $this->assertEquals(200000, $glRows->sum('credit'));
    }

    public function test_commission_accrual_is_idempotent(): void
    {
        session(['current_hotel_id' => $this->hotel->id]);

        $service = app(AgentCommissionService::class);
        $first = $service->accrue($this->reservation, $this->agent);
        $second = $service->accrue($this->reservation, $this->agent);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, AgentCommission::query()->where('reservation_id', $this->reservation->id)->count());
    }
}
