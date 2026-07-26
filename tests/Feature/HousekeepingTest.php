<?php

namespace Tests\Feature;

use App\Actions\Housekeeping\InspectRoomAction;
use App\Enums\HousekeepingAssignmentStatus;
use App\Enums\HousekeepingStatus;
use App\Enums\RoomStatus;
use App\Jobs\ProcessTelegramUpdate;
use App\Models\Floor;
use App\Models\Hotel;
use App\Models\HousekeepingLog;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\TelegramUser;
use App\Models\User;
use App\Notifications\RoomReadyNotification;
use App\Services\HousekeepingService;
use App\Telegram\TelegramCommandRouter;
use App\Telegram\TelegramConversationManager;
use App\Telegram\TelegramResponder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HousekeepingTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $housekeeper;

    private Room $room;

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

        $this->housekeeper = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->housekeeper->assignRole('housekeeping');

        $admin = User::factory()->create(['hotel_id' => null]);
        $admin->assignRole('admin');

        $roomType = RoomType::query()->create([
            'hotel_id' => $this->hotel->id,
            'code' => 'DLX',
            'name' => 'Deluxe',
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
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'number' => '101',
            'status' => RoomStatus::VacantDirty->value,
        ]);

        session(['current_hotel_id' => $this->hotel->id]);
    }

    public function test_housekeeping_status_board_is_accessible(): void
    {
        $user = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $user->assignRole('front_office');

        $this->actingAs($user)
            ->get('/housekeeping')
            ->assertOk();
    }

    public function test_log_status_change_syncs_room_status(): void
    {
        $service = app(HousekeepingService::class);

        $service->logStatusChange(
            $this->room,
            HousekeepingStatus::Cleaning->value,
            $this->housekeeper,
            'web',
        );

        $this->room->refresh();

        $this->assertEquals(RoomStatus::VacantDirty, $this->room->status);
        $this->assertDatabaseHas('housekeeping_logs', [
            'room_id' => $this->room->id,
            'status' => HousekeepingStatus::Cleaning->value,
        ]);
    }

    public function test_generate_daily_assignments_creates_rows(): void
    {
        $service = app(HousekeepingService::class);

        $assignments = $service->generateDailyAssignments($this->hotel->id);

        $this->assertGreaterThanOrEqual(1, $assignments->count());
        $this->assertDatabaseHas('housekeeping_assignments', [
            'room_id' => $this->room->id,
            'housekeeper_id' => $this->housekeeper->id,
            'status' => HousekeepingAssignmentStatus::Pending->value,
        ]);
    }

    public function test_inspect_room_marks_ready_and_notifies_front_office(): void
    {
        Notification::fake();

        $frontOffice = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $frontOffice->assignRole('front_office');

        $tgUser = TelegramUser::query()->create([
            'user_id' => $frontOffice->id,
            'chat_id' => 123456,
            'hotel_id' => $this->hotel->id,
            'linked_at' => now(),
            'is_active' => true,
        ]);

        HousekeepingLog::query()->create([
            'room_id' => $this->room->id,
            'status' => HousekeepingStatus::Clean->value,
            'changed_by' => $this->housekeeper->id,
            'changed_via' => 'web',
            'changed_at' => now(),
        ]);

        app(InspectRoomAction::class)->__invoke(
            $this->room,
            $this->housekeeper,
        );

        $this->room->refresh();
        $this->assertEquals(RoomStatus::VacantClean, $this->room->status);

        Notification::assertSentTo($tgUser, RoomReadyNotification::class);
    }

    public function test_assign_rooms_endpoint_creates_assignment(): void
    {
        $manager = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $manager->assignRole('manager');

        $this->actingAs($manager)
            ->post('/housekeeping/assignments', [
                'housekeeper_id' => $this->housekeeper->id,
                'room_ids' => [$this->room->id],
                'assignment_date' => now()->toDateString(),
                'shift' => 'morning',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('housekeeping_assignments', [
            'room_id' => $this->room->id,
            'housekeeper_id' => $this->housekeeper->id,
        ]);
    }

    public function test_telegram_roomstatus_updates_cleaning_status(): void
    {
        config(['telegram.bot_token' => null]);

        $this->housekeeper->givePermissionTo('housekeeping.update_status');

        $tgUser = TelegramUser::query()->create([
            'user_id' => $this->housekeeper->id,
            'chat_id' => 999001,
            'hotel_id' => $this->hotel->id,
            'linked_at' => now(),
            'is_active' => true,
        ]);

        $update = [
            'message' => [
                'from' => ['id' => 999001, 'username' => 'hk1'],
                'chat' => ['id' => 999001],
                'text' => '/roomstatus 101 cleaning',
            ],
        ];

        $job = new ProcessTelegramUpdate($update);
        $job->handle(
            app(TelegramCommandRouter::class),
            app(TelegramConversationManager::class),
            app(TelegramResponder::class),
        );

        $this->assertDatabaseHas('housekeeping_logs', [
            'room_id' => $this->room->id,
            'status' => HousekeepingStatus::Cleaning->value,
            'changed_via' => 'telegram',
        ]);
    }
}
