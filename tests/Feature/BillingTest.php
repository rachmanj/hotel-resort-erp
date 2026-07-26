<?php

namespace Tests\Feature;

use App\Actions\Reservations\CheckInGuestAction;
use App\Actions\Reservations\CheckOutGuestAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Floor;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\FolioPostingService;
use App\Services\TaxCalculator;
use Database\Seeders\AccountingDemoSeeder;
use Database\Seeders\BillingDemoSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $roomType;

    private Room $room;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDemoSeeder::class);

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

        $this->user = User::factory()->create(['hotel_id' => null]);
        $this->user->assignRole('admin');
        $this->hotel->users()->attach($this->user->id);

        session(['current_hotel_id' => $this->hotel->id]);

        (new ChartOfAccountsSeeder)->forHotel($this->hotel);
        (new AccountingDemoSeeder)->run();
    }

    public function test_tax_calculator_applies_service_charge_then_ppn(): void
    {
        $calculator = app(TaxCalculator::class);
        $result = $calculator->calculate(1000000, 'room');

        $this->assertEquals(1000000, $result['subtotal']);
        $this->assertEquals(100000, $result['service_charge']);
        $this->assertEquals(121000, $result['tax']);
        $this->assertEquals(1221000, $result['total']);
    }

    public function test_check_in_creates_folio_and_posts_room_charges(): void
    {
        $guest = Guest::query()->create(['full_name' => 'John Doe']);
        $reservation = $this->createReservation($guest);

        app(CheckInGuestAction::class)($reservation, $this->user);

        $reservation->refresh();
        $this->assertEquals(ReservationStatus::CheckedIn, $reservation->status);

        $folio = Folio::query()->where('reservation_id', $reservation->id)->first();
        $this->assertNotNull($folio);
        $this->assertStringStartsWith('FOL-', $folio->folio_no);

        $this->room->refresh();
        $this->assertEquals(RoomStatus::OccupiedClean, $this->room->status);

        $folioPostingService = app(FolioPostingService::class);
        $charges = $folioPostingService->getChargesTotal($folio);
        $this->assertEquals(1221000, $charges);
    }

    public function test_payment_reduces_folio_balance(): void
    {
        $guest = Guest::query()->create(['full_name' => 'Jane Doe']);
        $reservation = $this->createReservation($guest);
        app(CheckInGuestAction::class)($reservation, $this->user);

        $folio = Folio::query()->where('reservation_id', $reservation->id)->firstOrFail();
        $folioPostingService = app(FolioPostingService::class);

        $folioPostingService->postPayment($folio, 500000, 'cash', null, $this->user);

        $balance = $folioPostingService->getBalance($folio);
        $this->assertEquals(721000, $balance);
    }

    public function test_checkout_creates_guest_stay_and_closes_folio(): void
    {
        $guest = Guest::query()->create(['full_name' => 'Checkout Guest']);
        $reservation = $this->createReservation($guest);
        app(CheckInGuestAction::class)($reservation, $this->user);

        $reservationRoom = $reservation->reservationRooms()->first();
        app(CheckOutGuestAction::class)($reservationRoom, $this->user);

        $this->room->refresh();
        $this->assertEquals(RoomStatus::VacantDirty, $this->room->status);

        $this->assertDatabaseHas('guest_stays', [
            'guest_id' => $guest->id,
            'reservation_id' => $reservation->id,
        ]);

        $folio = Folio::query()->where('reservation_id', $reservation->id)->first();
        $this->assertEquals('closed', $folio->status->value);
    }

    public function test_blacklisted_guest_cannot_check_in(): void
    {
        $guest = Guest::query()->create([
            'full_name' => 'Blocked Guest',
            'is_blacklisted' => true,
            'blacklist_reason' => 'Previous misconduct',
        ]);
        $reservation = $this->createReservation($guest);

        $this->expectException(\InvalidArgumentException::class);
        app(CheckInGuestAction::class)($reservation, $this->user);
    }

    public function test_guests_index_requires_permission(): void
    {
        $this->get('/guests')->assertRedirect('/login');
    }

    public function test_guests_index_accessible_by_admin(): void
    {
        $this->actingAs($this->user)
            ->get('/guests')
            ->assertOk();
    }

    public function test_tax_rules_seeded(): void
    {
        $this->assertEquals(2, TaxRule::query()->count());
        $this->assertDatabaseHas('tax_rules', ['code' => 'ppn', 'rate_percent' => 11.00]);
        $this->assertDatabaseHas('tax_rules', ['code' => 'service_charge', 'rate_percent' => 10.00]);
    }

    private function createReservation(Guest $guest): Reservation
    {
        return app(CreateReservationAction::class)([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $guest->id,
            'arrival_date' => now()->toDateString(),
            'departure_date' => now()->addDay()->toDateString(),
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'adults' => 1,
            'created_by' => $this->user->id,
            'created_via' => 'web',
        ]);
    }
}
