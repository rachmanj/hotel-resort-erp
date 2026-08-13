<?php

namespace Tests\Feature;

use App\Actions\Reservations\CheckInGuestAction;
use App\Actions\Reservations\CheckOutGuestAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Enums\ReservationRoomStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Exceptions\OutstandingBalanceException;
use App\Models\Company;
use App\Models\Floor;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\FolioPostingService;
use Database\Seeders\AccountingDemoSeeder;
use Database\Seeders\BillingDemoSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CheckoutSettlementTest extends TestCase
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

    public function test_checkout_blocked_when_balance_is_outstanding(): void
    {
        $reservationRoom = $this->checkInReservation();

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservation-rooms/{$reservationRoom->id}/checkout")
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionHas('outstanding_balance');

        $reservationRoom->refresh();
        $this->assertEquals(ReservationRoomStatus::CheckedIn, $reservationRoom->status);
    }

    public function test_checkout_action_throws_when_balance_is_outstanding(): void
    {
        $reservationRoom = $this->checkInReservation();

        $this->expectException(OutstandingBalanceException::class);

        app(CheckOutGuestAction::class)($reservationRoom, $this->user);
    }

    public function test_checkout_allowed_when_balance_is_zero_after_payment(): void
    {
        $reservationRoom = $this->checkInReservation();
        $folio = Folio::query()->where('reservation_id', $reservationRoom->reservation_id)->firstOrFail();
        $folioPostingService = app(FolioPostingService::class);

        $folioPostingService->postPayment(
            $folio,
            $folioPostingService->getBalance($folio),
            'cash',
            null,
            $this->user,
        );

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservation-rooms/{$reservationRoom->id}/checkout")
            ->assertRedirect()
            ->assertSessionHas('success');

        $reservationRoom->refresh();
        $this->assertEquals(ReservationRoomStatus::CheckedOut, $reservationRoom->status);
        $this->assertEquals(ReservationStatus::CheckedOut, $reservationRoom->reservation->fresh()->status);
    }

    public function test_checkout_allowed_when_company_billed_regardless_of_balance(): void
    {
        $reservationRoom = $this->checkInReservation();
        $folio = Folio::query()->where('reservation_id', $reservationRoom->reservation_id)->firstOrFail();

        $company = Company::query()->create(['name' => 'ACME Corp']);
        $folio->update(['company_id' => $company->id]);

        $folioPostingService = app(FolioPostingService::class);
        $this->assertGreaterThan(0, $folioPostingService->getBalance($folio));

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservation-rooms/{$reservationRoom->id}/checkout")
            ->assertRedirect()
            ->assertSessionHas('success');

        $reservationRoom->refresh();
        $this->assertEquals(ReservationRoomStatus::CheckedOut, $reservationRoom->status);

        $folio->refresh();
        $this->assertEquals('open', $folio->status->value);
    }

    private function checkInReservation(): ReservationRoom
    {
        $guest = Guest::query()->create(['full_name' => 'Settlement Guest']);
        $reservation = app(CreateReservationAction::class)([
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

        app(CheckInGuestAction::class)($reservation, $this->user);

        return $reservation->reservationRooms()->firstOrFail();
    }
}
