<?php

namespace Tests\Feature;

use App\Actions\Reservations\CheckInGuestAction;
use App\Actions\Reservations\ConfirmReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Enums\FolioItemType;
use App\Enums\OrderType;
use App\Enums\RestaurantTableArea;
use App\Enums\RestaurantTableStatus;
use App\Enums\RoomStatus;
use App\Models\Floor;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\AccountingDemoSeeder;
use Database\Seeders\BillingDemoSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FbOrderChargeToRoomTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $user;

    private MenuItem $menuItem;

    private RestaurantTable $table;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDemoSeeder::class);
        $this->seed(DepartmentSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Test Hotel',
            'code' => 'TST',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $this->hotel->id]);

        (new ChartOfAccountsSeeder)->forHotel($this->hotel);
        (new AccountingDemoSeeder)->run();

        $this->user = User::factory()->create(['hotel_id' => null]);
        $this->user->givePermissionTo(['fb.view', 'fb.manage', 'fb.orders.create']);
        $this->hotel->users()->attach($this->user->id);

        $category = MenuCategory::query()->create([
            'name' => 'Mains',
            'sort_order' => 1,
        ]);

        $this->menuItem = MenuItem::query()->create([
            'menu_category_id' => $category->id,
            'name' => 'Nasi Goreng',
            'price' => 75000,
            'is_available' => true,
        ]);

        $this->table = RestaurantTable::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'T1',
            'area' => RestaurantTableArea::Indoor->value,
            'status' => RestaurantTableStatus::Available->value,
        ]);

        $roomType = RoomType::query()->create([
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

        $room = Room::query()->create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'number' => '101',
            'status' => RoomStatus::VacantClean->value,
        ]);

        $guest = Guest::query()->create(['full_name' => 'In-House Guest']);

        $this->reservation = app(CreateReservationAction::class)([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $guest->id,
            'arrival_date' => now()->toDateString(),
            'departure_date' => now()->addDay()->toDateString(),
            'room_type_id' => $roomType->id,
            'room_id' => $room->id,
            'adults' => 1,
            'created_by' => $this->user->id,
            'created_via' => 'web',
        ]);

        app(ConfirmReservationAction::class)($this->reservation, $this->user);

        app(CheckInGuestAction::class)($this->reservation->refresh(), $this->user);
    }

    public function test_create_order_with_charge_to_room_posts_folio_charge(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post('/fb/orders', [
                'order_type' => OrderType::DineIn->value,
                'restaurant_table_id' => $this->table->id,
                'reservation_id' => $this->reservation->id,
                'charged_to_room' => true,
                'items' => [
                    ['menu_item_id' => $this->menuItem->id, 'quantity' => 2],
                ],
            ]);

        $order = Order::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('fb.orders.show', $order));

        $this->assertTrue($order->charged_to_room);
        $this->assertNotNull($order->folio_item_id);
        $this->assertSame($this->reservation->id, $order->reservation_id);
        $this->assertEquals(150000.0, (float) $order->total_amount);

        $folio = Folio::query()->where('reservation_id', $this->reservation->id)->firstOrFail();

        $this->assertDatabaseHas('folio_items', [
            'folio_id' => $folio->id,
            'id' => $order->folio_item_id,
            'item_type' => FolioItemType::Fb->value,
            'amount' => 150000,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
        ]);
    }

    public function test_second_charge_to_room_attempt_returns_friendly_error(): void
    {
        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post('/fb/orders', [
                'order_type' => OrderType::DineIn->value,
                'restaurant_table_id' => $this->table->id,
                'reservation_id' => $this->reservation->id,
                'charged_to_room' => true,
                'items' => [
                    ['menu_item_id' => $this->menuItem->id, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();
        $folioItemCountBefore = FolioItem::query()->where('folio_id', $order->folioItem->folio_id)->count();

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/fb/orders/{$order->id}/charge-to-room", [
                'reservation_id' => $this->reservation->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Order is already charged to room.');

        $this->assertSame($folioItemCountBefore, FolioItem::query()->where('folio_id', $order->folioItem->folio_id)->count());
    }
}
