<?php

namespace Tests\Feature;

use App\Enums\AgentType;
use App\Enums\DirectChannel;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Agent;
use App\Models\Company;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReservationRevampPhase1Test extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $roomType;

    private Room $room;

    private User $user;

    private User $marketingUser;

    private Agent $otaAgent;

    private Agent $travelAgent;

    private Company $company;

    private Agent $corporateAgent;

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

        $this->roomType = RoomType::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe',
            'code' => 'DLX-T',
            'max_occupancy' => 2,
            'base_rate' => 500000,
            'is_active' => true,
        ]);

        $floor = Floor::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Floor 1',
            'level' => 1,
        ]);

        $this->room = Room::query()->create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $this->roomType->id,
            'floor_id' => $floor->id,
            'number' => '101',
            'status' => RoomStatus::VacantClean->value,
        ]);

        $this->user = User::factory()->create(['hotel_id' => null]);
        $this->user->assignRole('admin');
        $this->hotel->users()->attach($this->user->id);

        $this->marketingUser = User::factory()->create(['name' => 'Maya Test']);
        $this->marketingUser->assignRole('marketing');

        $this->otaAgent = Agent::query()->create([
            'hotel_id' => $this->hotel->id,
            'agent_type' => AgentType::Ota->value,
            'name' => 'Traveloka',
            'code' => 'TRV',
            'is_active' => true,
        ]);

        $this->travelAgent = Agent::query()->create([
            'hotel_id' => $this->hotel->id,
            'agent_type' => AgentType::Travel->value,
            'name' => 'Bali Tours',
            'code' => 'BT',
            'is_active' => true,
        ]);

        $this->company = Company::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'PT Example Corp',
            'code' => 'EXC',
            'is_active' => true,
        ]);

        $this->corporateAgent = Agent::query()->create([
            'hotel_id' => $this->hotel->id,
            'agent_type' => AgentType::Corporate->value,
            'name' => 'PT Example Corp Agent',
            'code' => 'EXC-AG',
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        (new ChartOfAccountsSeeder)->forHotel($this->hotel);
    }

    public function test_reservations_table_has_marketing_columns(): void
    {
        $this->assertTrue(\Schema::hasColumn('reservations', 'direct_channel'));
        $this->assertTrue(\Schema::hasColumn('reservations', 'marketing_user_id'));
        $this->assertTrue(\Schema::hasColumn('reservations', 'is_marketing_non_agent'));
        $this->assertTrue(\Schema::hasColumn('reservations', 'marketing_non_agent_confirmed_at'));

        $reservation = Reservation::query()->create([
            'hotel_id' => $this->hotel->id,
            'reservation_code' => 'RES-TEST-0001',
            'guest_id' => Guest::query()->create(['full_name' => 'Schema Guest'])->id,
            'source' => ReservationSource::Walkin->value,
            'status' => ReservationStatus::Tentative->value,
            'arrival_date' => now()->addDay(),
            'departure_date' => now()->addDays(2),
        ]);

        $this->assertFalse($reservation->fresh()->is_marketing_non_agent);
    }

    public function test_can_create_reservation_for_each_category(): void
    {
        $cases = [
            ['source' => 'ota', 'agent_id' => $this->otaAgent->id],
            ['source' => 'travel_agent', 'agent_id' => $this->travelAgent->id],
            ['source' => 'corporate', 'company_id' => $this->company->id],
            ['source' => 'direct', 'direct_channel' => DirectChannel::Web->value],
            ['source' => 'walkin'],
        ];

        foreach ($cases as $index => $case) {
            $payload = $this->basePayload($case['source'], $index);
            $payload = array_merge($payload, $case);

            $this->actingAs($this->user)
                ->withSession(['current_hotel_id' => $this->hotel->id])
                ->post(route('reservations.store'), $payload)
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('reservations', [
                'source' => $case['source'],
                'marketing_user_id' => $this->marketingUser->id,
                'guest_id' => Guest::query()->where('phone', $payload['guest']['phone'])->value('id'),
            ]);
        }
    }

    public function test_direct_without_channel_is_rejected(): void
    {
        $payload = $this->basePayload('direct', 99);
        unset($payload['direct_channel']);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->from(route('reservations.create'))
            ->post(route('reservations.store'), $payload)
            ->assertSessionHasErrors('direct_channel');
    }

    public function test_direct_channel_rejected_for_non_direct_source(): void
    {
        $payload = $this->basePayload('walkin', 100);
        $payload['direct_channel'] = DirectChannel::Phone->value;

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->from(route('reservations.create'))
            ->post(route('reservations.store'), $payload)
            ->assertSessionHasErrors('direct_channel');
    }

    public function test_marketing_user_without_role_is_rejected(): void
    {
        $nonMarketing = User::factory()->create();
        $payload = $this->basePayload('walkin', 101);
        $payload['marketing_user_id'] = $nonMarketing->id;

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->from(route('reservations.create'))
            ->post(route('reservations.store'), $payload)
            ->assertSessionHasErrors('marketing_user_id');
    }

    public function test_check_in_can_set_marketing_non_agent_and_stamp_timestamp(): void
    {
        $guest = Guest::query()->create(['full_name' => 'Check-in Guest', 'phone' => '081122233344']);

        $reservation = Reservation::query()->create([
            'hotel_id' => $this->hotel->id,
            'reservation_code' => 'RES-CHK-0001',
            'guest_id' => $guest->id,
            'source' => ReservationSource::Direct->value,
            'direct_channel' => DirectChannel::Phone->value,
            'marketing_user_id' => $this->marketingUser->id,
            'status' => ReservationStatus::Confirmed->value,
            'arrival_date' => now()->toDateString(),
            'departure_date' => now()->addDay()->toDateString(),
        ]);

        $reservation->reservationRooms()->create([
            'room_id' => $this->room->id,
            'room_type_id' => $this->roomType->id,
            'nightly_rate' => 500000,
            'status' => 'booked',
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('reservations.checkin', $reservation), [
                'is_marketing_non_agent' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $reservation->refresh();
        $this->assertTrue($reservation->is_marketing_non_agent);
        $this->assertNotNull($reservation->marketing_non_agent_confirmed_at);
    }

    public function test_group_booking_routes_return_not_found(): void
    {
        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/groups')
            ->assertNotFound();

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/groups/create')
            ->assertNotFound();
    }

    public function test_reservation_linked_to_group_still_loads(): void
    {
        $guest = Guest::query()->create(['full_name' => 'Group Member']);

        $group = ReservationGroup::query()->create([
            'hotel_id' => $this->hotel->id,
            'group_code' => 'GRP-TEST-01',
            'name' => 'Legacy Group',
            'group_type' => 'single_multi_room',
            'status' => 'confirmed',
            'arrival_date' => now()->addDay(),
            'departure_date' => now()->addDays(2),
        ]);

        $reservation = Reservation::query()->create([
            'hotel_id' => $this->hotel->id,
            'reservation_code' => 'RES-GRP-0001',
            'guest_id' => $guest->id,
            'reservation_group_id' => $group->id,
            'source' => ReservationSource::Agent->value,
            'status' => ReservationStatus::Confirmed->value,
            'arrival_date' => now()->addDay(),
            'departure_date' => now()->addDays(2),
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('reservations.show', $reservation))
            ->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(string $source, int $suffix): array
    {
        return [
            'arrival_date' => now()->addDays(10 + ($suffix * 5))->toDateString(),
            'departure_date' => now()->addDays(12 + ($suffix * 5))->toDateString(),
            'room_type_id' => $this->roomType->id,
            'source' => $source,
            'marketing_user_id' => $this->marketingUser->id,
            'guest' => [
                'full_name' => "Guest {$suffix}",
                'phone' => '0812'.str_pad((string) $suffix, 8, '0', STR_PAD_LEFT),
            ],
        ];
    }
}
