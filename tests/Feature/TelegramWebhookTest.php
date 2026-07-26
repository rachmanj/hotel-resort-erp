<?php

namespace Tests\Feature;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Hotel;
use App\Models\TelegramUser;
use App\Models\User;
use App\Telegram\TelegramCommandRouter;
use App\Telegram\TelegramConversationManager;
use App\Telegram\TelegramResponder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_webhook_returns_200_and_dispatches_job(): void
    {
        Queue::fake();

        config(['telegram.webhook_secret' => 'test-secret']);

        $payload = [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 123, 'username' => 'testuser'],
                'chat' => ['id' => 456, 'type' => 'private'],
                'text' => '/start',
            ],
        ];

        $response = $this->postJson('/api/telegram/webhook', $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);

        $response->assertOk();
        Queue::assertPushed(ProcessTelegramUpdate::class);
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        config(['telegram.webhook_secret' => 'test-secret']);

        $response = $this->postJson('/api/telegram/webhook', [], [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
        ]);

        $response->assertUnauthorized();
    }

    public function test_webhook_accepts_request_without_secret_when_not_configured(): void
    {
        Queue::fake();

        config(['telegram.webhook_secret' => null]);

        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'from' => ['id' => 1],
                'chat' => ['id' => 2],
                'text' => '/start',
            ],
        ]);

        $response->assertOk();
        Queue::assertPushed(ProcessTelegramUpdate::class);
    }

    public function test_process_update_creates_telegram_user(): void
    {
        config(['telegram.bot_token' => null]);

        $update = [
            'message' => [
                'from' => ['id' => 111, 'username' => 'staff1'],
                'chat' => ['id' => 999888],
                'text' => '/start',
            ],
        ];

        $job = new ProcessTelegramUpdate($update);
        $job->handle(
            app(TelegramCommandRouter::class),
            app(TelegramConversationManager::class),
            app(TelegramResponder::class),
        );

        $this->assertDatabaseHas('telegram_users', [
            'chat_id' => 999888,
            'telegram_username' => 'staff1',
        ]);
    }

    public function test_link_command_links_account(): void
    {
        config(['telegram.bot_token' => null]);

        $hotel = Hotel::query()->create([
            'name' => 'Test Hotel',
            'code' => 'TST',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['hotel_id' => $hotel->id]);
        $user->assignRole('front_office');

        TelegramUser::query()->create([
            'user_id' => $user->id,
            'link_code' => 'ABC123',
            'link_code_expires_at' => now()->addMinutes(10),
            'is_active' => true,
        ]);

        $chatUser = TelegramUser::query()->create([
            'chat_id' => 12345,
            'is_active' => true,
        ]);

        $update = [
            'message' => [
                'from' => ['id' => 12345, 'username' => 'staff1'],
                'chat' => ['id' => 12345],
                'text' => '/link ABC123',
            ],
        ];

        $job = new ProcessTelegramUpdate($update);
        $job->handle(
            app(TelegramCommandRouter::class),
            app(TelegramConversationManager::class),
            app(TelegramResponder::class),
        );

        $chatUser->refresh();

        $this->assertEquals($user->id, $chatUser->user_id);
        $this->assertEquals($hotel->id, $chatUser->hotel_id);
        $this->assertNotNull($chatUser->linked_at);
    }
}
