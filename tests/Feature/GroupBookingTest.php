<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GroupBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_booking_web_routes_are_not_registered(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $hotel = Hotel::query()->create([
            'name' => 'Test Hotel',
            'code' => 'TST',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['hotel_id' => null]);
        $user->assignRole('admin');
        $hotel->users()->attach($user->id);

        $this->actingAs($user)
            ->withSession(['current_hotel_id' => $hotel->id])
            ->get('/groups')
            ->assertNotFound();

        $this->actingAs($user)
            ->withSession(['current_hotel_id' => $hotel->id])
            ->post('/groups', ['name' => 'Test'])
            ->assertNotFound();
    }
}
