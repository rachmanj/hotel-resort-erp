<?php

namespace Tests\Feature;

use App\Enums\AgentType;
use App\Enums\CommissionBasis;
use App\Models\Agent;
use App\Models\Hotel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgentPortalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private Agent $agentA;

    private Agent $agentB;

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

        $this->agentA = Agent::query()->create([
            'hotel_id' => $this->hotel->id,
            'agent_type' => AgentType::Travel->value,
            'name' => 'Agent A',
            'code' => 'AGA',
            'commission_percent' => 10,
            'commission_basis' => CommissionBasis::NetRoom->value,
            'is_active' => true,
        ]);

        $this->agentB = Agent::query()->create([
            'hotel_id' => $this->hotel->id,
            'agent_type' => AgentType::Travel->value,
            'name' => 'Agent B',
            'code' => 'AGB',
            'commission_percent' => 10,
            'commission_basis' => CommissionBasis::NetRoom->value,
            'is_active' => true,
        ]);
    }

    public function test_agent_portal_requires_agent_role(): void
    {
        $user = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $user->assignRole('admin');
        $this->hotel->users()->attach($user->id);

        $this->actingAs($user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/agent-portal/bookings')
            ->assertForbidden();
    }

    public function test_agent_can_access_own_bookings_page(): void
    {
        $user = User::factory()->create([
            'hotel_id' => $this->hotel->id,
            'agent_id' => $this->agentA->id,
        ]);
        $user->assignRole('agent');
        $this->agentA->update(['user_id' => $user->id]);
        $this->hotel->users()->attach($user->id);

        $this->actingAs($user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/agent-portal/bookings')
            ->assertOk();
    }
}
