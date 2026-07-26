<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InventoryAuthorizationTest extends TestCase
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

    public function test_inventory_index_requires_authentication(): void
    {
        $this->get('/inventory')->assertRedirect('/login');
    }

    public function test_inventory_index_accessible_by_admin(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/inventory')
            ->assertOk();
    }

    public function test_inventory_index_denied_without_permission(): void
    {
        $this->actingAs($this->frontOffice)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/inventory')
            ->assertForbidden();
    }
}
