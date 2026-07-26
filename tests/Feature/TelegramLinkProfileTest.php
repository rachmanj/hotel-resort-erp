<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\TelegramUser;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TelegramLinkProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Hotel $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $hotel = Hotel::query()->create([
            'name' => 'Test Hotel',
            'code' => 'TST',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create(['hotel_id' => $hotel->id]);
        $this->user->assignRole('front_office');
        $this->hotel = $hotel;
    }

    public function test_telegram_link_page_requires_authentication(): void
    {
        $this->get('/profile/telegram')->assertRedirect('/login');
    }

    public function test_telegram_link_page_renders_for_authenticated_user(): void
    {
        session(['current_hotel_id' => $this->hotel->id]);

        $this->actingAs($this->user)
            ->get('/profile/telegram')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/TelegramLink')
                ->has('botUsername')
                ->where('isLinked', false),
            );
    }

    public function test_generate_link_code_creates_code(): void
    {
        session(['current_hotel_id' => $this->hotel->id]);

        $this->actingAs($this->user)
            ->post('/profile/telegram/generate-code')
            ->assertRedirect();

        $telegramUser = TelegramUser::query()
            ->withoutGlobalScope('hotel')
            ->where('user_id', $this->user->id)
            ->first();

        $this->assertNotNull($telegramUser);
        $this->assertNotNull($telegramUser->link_code);
        $this->assertEquals(6, strlen($telegramUser->link_code));
        $this->assertTrue($telegramUser->link_code_expires_at->isFuture());
    }
}
