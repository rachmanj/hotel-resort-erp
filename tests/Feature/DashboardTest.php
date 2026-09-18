<?php

namespace Tests\Feature;

use App\Enums\FolioItemType;
use App\Enums\FolioStatus;
use App\Enums\ReservationRoomStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Floor;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\RevenueCategory;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private int $nextRoomNumber = 201;

    private Hotel $hotel;

    private Hotel $otherHotel;

    private RoomType $roomType;

    private Room $room;

    private User $frontOfficeUser;

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

        $this->otherHotel = Hotel::query()->create([
            'name' => 'Other Hotel',
            'code' => 'OTH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $this->roomType = RoomType::query()->create([
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

        $this->room = Room::query()->create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $this->roomType->id,
            'floor_id' => $floor->id,
            'number' => '101',
            'status' => RoomStatus::VacantClean->value,
        ]);

        $this->frontOfficeUser = User::factory()->create(['hotel_id' => null]);
        $this->frontOfficeUser->assignRole('front_office');
        $this->hotel->users()->attach($this->frontOfficeUser->id);

        session(['current_hotel_id' => $this->hotel->id]);
    }

    public function test_dashboard_renders_for_authenticated_front_office_user(): void
    {
        $response = $this->actingAs($this->frontOfficeUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->has('occupancy')
            ->has('checkinsToday')
            ->has('occupiedRooms')
            ->has('sellableRooms')
            ->has('revenueToday')
            ->has('roomStatusSummary')
            ->has('rooms')
            ->has('arrivalsToday')
            ->has('departuresToday')
            ->has('occupancySeries')
            ->has('occupancyDelta')
            ->has('inHouseGuests')
            ->has('revenueMix')
        );
    }

    public function test_arrivals_and_departures_only_contain_todays_reservations_for_current_hotel(): void
    {
        $guest = Guest::query()->create(['full_name' => 'Arrival Guest']);

        $arrivalToday = $this->createReservation(
            guest: $guest,
            hotel: $this->hotel,
            arrivalDate: today(),
            departureDate: today()->addDay(),
            reservationCode: 'ARR-TODAY',
        );

        $this->createReservation(
            guest: $guest,
            hotel: $this->hotel,
            arrivalDate: today()->addDay(),
            departureDate: today()->addDays(2),
            reservationCode: 'ARR-TOMORROW',
        );

        $this->createReservation(
            guest: $guest,
            hotel: $this->otherHotel,
            arrivalDate: today(),
            departureDate: today()->addDay(),
            reservationCode: 'ARR-OTHER',
        );

        $this->createReservation(
            guest: $guest,
            hotel: $this->hotel,
            arrivalDate: today(),
            departureDate: today()->addDay(),
            reservationCode: 'ARR-CANCELLED',
            reservationStatus: ReservationStatus::Cancelled,
        );

        $departureToday = $this->createReservation(
            guest: $guest,
            hotel: $this->hotel,
            arrivalDate: today()->subDay(),
            departureDate: today(),
            reservationCode: 'DEP-TODAY',
        );

        $this->createReservation(
            guest: $guest,
            hotel: $this->hotel,
            arrivalDate: today()->subDays(2),
            departureDate: today()->addDay(),
            reservationCode: 'DEP-TOMORROW',
        );

        $response = $this->actingAs($this->frontOfficeUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->has('arrivalsToday', 1)
            ->where('arrivalsToday.0.id', $arrivalToday->reservationRooms()->first()->id)
            ->where('arrivalsToday.0.guest_name', 'Arrival Guest')
            ->has('departuresToday', 1)
            ->where('departuresToday.0.id', $departureToday->reservationRooms()->first()->id)
        );
    }

    public function test_occupancy_series_has_fourteen_entries(): void
    {
        $response = $this->actingAs($this->frontOfficeUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->has('occupancySeries', 14)
            ->where('occupancySeries.0.date', today()->subDays(13)->toDateString())
            ->where('occupancySeries.13.date', today()->toDateString())
        );
    }

    public function test_revenue_mix_shares_sum_to_about_one_hundred_when_revenue_exists(): void
    {
        $roomCategory = RevenueCategory::query()->create([
            'hotel_id' => $this->hotel->id,
            'code' => 'room',
            'name' => 'Room Revenue',
            'coa_account_code' => '4-1000',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $fbCategory = RevenueCategory::query()->create([
            'hotel_id' => $this->hotel->id,
            'code' => 'fb',
            'name' => 'F&B Revenue',
            'coa_account_code' => '4-2000',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $guest = Guest::query()->create(['full_name' => 'Revenue Guest']);
        $folio = Folio::query()->create([
            'hotel_id' => $this->hotel->id,
            'folio_no' => 'FOL-DASH-0001',
            'guest_id' => $guest->id,
            'type' => 'master',
            'status' => FolioStatus::Open->value,
            'opened_at' => now(),
        ]);

        FolioItem::query()->create([
            'folio_id' => $folio->id,
            'revenue_category_id' => $roomCategory->id,
            'item_type' => FolioItemType::Room->value,
            'description' => 'Room charge',
            'quantity' => 1,
            'unit_price' => 750000,
            'amount' => 750000,
            'posted_at' => now(),
        ]);

        FolioItem::query()->create([
            'folio_id' => $folio->id,
            'revenue_category_id' => $fbCategory->id,
            'item_type' => FolioItemType::Fb->value,
            'description' => 'Restaurant charge',
            'quantity' => 1,
            'unit_price' => 250000,
            'amount' => 250000,
            'posted_at' => now(),
        ]);

        $response = $this->actingAs($this->frontOfficeUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(function ($page): void {
            $page->component('Dashboard/Index')
                ->has('revenueMix', 2);

            $shares = collect($page->toArray()['props']['revenueMix'])->pluck('share')->sum();
            $this->assertEqualsWithDelta(100.0, $shares, 0.2);
        });
    }

    public function test_dashboard_renders_when_there_is_no_data(): void
    {
        $response = $this->actingAs($this->frontOfficeUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->where('arrivalsToday', [])
            ->where('departuresToday', [])
            ->where('revenueMix', [])
            ->where('inHouseGuests', 0)
            ->where('occupancyDelta', 0)
            ->has('occupancySeries', 14)
        );
    }

    private function createReservation(
        Guest $guest,
        Hotel $hotel,
        Carbon $arrivalDate,
        Carbon $departureDate,
        string $reservationCode,
        ReservationStatus $reservationStatus = ReservationStatus::Confirmed,
    ): Reservation {
        $roomType = $hotel->id === $this->hotel->id
            ? $this->roomType
            : RoomType::query()->create([
                'hotel_id' => $hotel->id,
                'name' => 'Standard',
                'code' => 'STD-'.$hotel->code,
                'max_occupancy' => 2,
                'base_rate' => 500000,
                'is_active' => true,
            ]);

        $floor = Floor::query()->create([
            'hotel_id' => $hotel->id,
            'name' => 'Floor 1',
            'level' => 1,
        ]);

        $roomNumber = (string) $this->nextRoomNumber++;
        $room = Room::query()->create([
            'hotel_id' => $hotel->id,
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'number' => $roomNumber,
            'status' => RoomStatus::VacantClean->value,
        ]);

        $reservation = Reservation::query()->create([
            'hotel_id' => $hotel->id,
            'reservation_code' => $reservationCode,
            'guest_id' => $guest->id,
            'source' => ReservationSource::Walkin->value,
            'status' => $reservationStatus->value,
            'arrival_date' => $arrivalDate->toDateString(),
            'departure_date' => $departureDate->toDateString(),
            'adults' => 2,
            'children' => 1,
        ]);

        ReservationRoom::query()->create([
            'reservation_id' => $reservation->id,
            'room_id' => $room->id,
            'room_type_id' => $roomType->id,
            'nightly_rate' => 1000000,
            'status' => ReservationRoomStatus::Booked->value,
        ]);

        return $reservation;
    }
}
