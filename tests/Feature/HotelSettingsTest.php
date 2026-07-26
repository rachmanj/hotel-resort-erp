<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class HotelSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $admin;

    private User $frontOffice;

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

        $this->admin = User::factory()->create(['hotel_id' => null]);
        $this->admin->assignRole('admin');
        $this->hotel->users()->attach($this->admin->id);

        $this->frontOffice = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->frontOffice->assignRole('front_office');
        $this->hotel->users()->attach($this->frontOffice->id);
    }

    public function test_hotel_settings_requires_authentication(): void
    {
        $this->get('/admin/hotel-settings')->assertRedirect('/login');
    }

    public function test_hotel_settings_accessible_by_admin(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/admin/hotel-settings')
            ->assertOk();
    }

    public function test_hotel_settings_denied_without_permission(): void
    {
        $this->actingAs($this->frontOffice)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/admin/hotel-settings')
            ->assertForbidden();
    }

    public function test_admin_can_update_hotel_settings(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->put('/admin/hotel-settings', [
                'name' => 'Updated Hotel Name',
                'address' => 'Jl. Test 123',
                'currency' => 'IDR',
                'timezone' => 'Asia/Jakarta',
                'default_checkin_time' => '15:00',
                'default_checkout_time' => '11:00',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->hotel->refresh();
        $this->assertEquals('Updated Hotel Name', $this->hotel->name);
        $this->assertEquals('Jl. Test 123', $this->hotel->address);
        $this->assertEquals('Asia/Jakarta', $this->hotel->timezone);
    }
}
