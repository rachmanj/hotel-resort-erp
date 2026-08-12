<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Hotel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ActivityLogIndexTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $admin;

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

        session(['current_hotel_id' => $this->hotel->id]);
    }

    public function test_admin_can_view_activity_logs_index(): void
    {
        ActivityLog::query()->create([
            'hotel_id' => $this->hotel->id,
            'user_id' => $this->admin->id,
            'subject_type' => User::class,
            'subject_id' => $this->admin->id,
            'event' => 'updated',
            'properties' => ['description' => 'User profile updated'],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.activity-logs.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/ActivityLogs/Index')
            ->has('logs.data', 1)
            ->where('logs.data.0.description', 'User profile updated')
        );
    }

    public function test_user_without_permission_cannot_view_activity_logs(): void
    {
        $user = User::factory()->create(['hotel_id' => null]);
        $user->assignRole('front_office');
        $this->hotel->users()->attach($user->id);

        $this->actingAs($user)
            ->get(route('admin.activity-logs.index'))
            ->assertForbidden();
    }
}
