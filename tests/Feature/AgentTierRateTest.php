<?php

namespace Tests\Feature;

use App\Enums\AgentRateCategory;
use App\Enums\AgentType;
use App\Enums\CommissionBasis;
use App\Models\Agent;
use App\Models\AgentRate;
use App\Models\AgentTierRate;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\User;
use App\Services\AgentRateService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AgentTierRateTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $roomType;

    private Agent $agent;

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

        $this->roomType = RoomType::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe',
            'code' => 'DLX',
            'max_occupancy' => 2,
            'base_rate' => 1690000,
            'is_active' => true,
        ]);

        $this->agent = Agent::query()->create([
            'hotel_id' => $this->hotel->id,
            'agent_type' => AgentType::Travel->value,
            'rate_category' => AgentRateCategory::A->value,
            'name' => 'Travel Agent Co',
            'code' => 'TAC',
            'commission_percent' => 0,
            'commission_basis' => CommissionBasis::NetRoom->value,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->admin->assignRole('admin');
        $this->hotel->users()->attach($this->admin->id);
    }

    public function test_resolves_tier_rate_for_agent_in_tier(): void
    {
        AgentTierRate::query()->create([
            'rate_category' => AgentRateCategory::A->value,
            'room_type_id' => $this->roomType->id,
            'nightly_rate' => 1300000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'is_active' => true,
        ]);

        $checkin = Carbon::parse('2026-06-01');
        $checkout = Carbon::parse('2026-06-03');

        $rate = app(AgentRateService::class)->resolveNightlyRate(
            $this->agent,
            $this->roomType->id,
            $checkin,
            $checkout,
        );

        $this->assertSame('1300000.00', $rate);
    }

    public function test_individual_agent_override_wins_over_tier_rate(): void
    {
        AgentTierRate::query()->create([
            'rate_category' => AgentRateCategory::A->value,
            'room_type_id' => $this->roomType->id,
            'nightly_rate' => 1300000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'is_active' => true,
        ]);

        AgentRate::query()->create([
            'agent_id' => $this->agent->id,
            'room_type_id' => $this->roomType->id,
            'nightly_rate' => 1100000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'is_active' => true,
        ]);

        $checkin = Carbon::parse('2026-06-01');
        $checkout = Carbon::parse('2026-06-03');

        $rate = app(AgentRateService::class)->resolveNightlyRate(
            $this->agent,
            $this->roomType->id,
            $checkin,
            $checkout,
        );

        $this->assertSame('1100000.00', $rate);
    }

    public function test_dates_outside_valid_window_return_null(): void
    {
        AgentTierRate::query()->create([
            'rate_category' => AgentRateCategory::A->value,
            'room_type_id' => $this->roomType->id,
            'nightly_rate' => 1300000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-06-30',
            'is_active' => true,
        ]);

        $checkin = Carbon::parse('2026-07-01');
        $checkout = Carbon::parse('2026-07-03');

        $rate = app(AgentRateService::class)->resolveNightlyRate(
            $this->agent,
            $this->roomType->id,
            $checkin,
            $checkout,
        );

        $this->assertNull($rate);
    }

    public function test_inactive_tier_rates_are_ignored(): void
    {
        AgentTierRate::query()->create([
            'rate_category' => AgentRateCategory::A->value,
            'room_type_id' => $this->roomType->id,
            'nightly_rate' => 1300000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'is_active' => false,
        ]);

        $checkin = Carbon::parse('2026-06-01');
        $checkout = Carbon::parse('2026-06-03');

        $rate = app(AgentRateService::class)->resolveNightlyRate(
            $this->agent,
            $this->roomType->id,
            $checkin,
            $checkout,
        );

        $this->assertNull($rate);
    }

    public function test_store_validation_failure_returns_errors(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->from(route('admin.agent-tier-rates.index'))
            ->post(route('admin.agent-tier-rates.store'), [
                'rate_category' => '',
                'room_type_id' => $this->roomType->id,
                'nightly_rate' => -100,
                'valid_from' => '2026-12-31',
                'valid_to' => '2026-01-01',
            ])
            ->assertRedirect(route('admin.agent-tier-rates.index'))
            ->assertSessionHasErrors(['rate_category', 'nightly_rate', 'valid_to']);

        $this->assertDatabaseCount('agent_tier_rates', 0);
    }

    public function test_bulk_store_upserts_rates_and_skips_empty_cells(): void
    {
        AgentTierRate::query()->create([
            'rate_category' => AgentRateCategory::A->value,
            'room_type_id' => $this->roomType->id,
            'nightly_rate' => 1000000,
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->from(route('admin.agent-tier-rates.index'))
            ->post(route('admin.agent-tier-rates.bulk-store'), [
                'valid_from' => '2026-01-01',
                'valid_to' => '2026-12-31',
                'rates' => [
                    [
                        'room_type_id' => $this->roomType->id,
                        'A' => 1300000,
                        'B' => 1200000,
                        'C' => null,
                        'D' => 1100000,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.agent-tier-rates.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('agent_tier_rates', 3);
        $this->assertDatabaseHas('agent_tier_rates', [
            'rate_category' => AgentRateCategory::A->value,
            'room_type_id' => $this->roomType->id,
            'nightly_rate' => '1300000.00',
            'valid_from' => '2026-01-01',
            'valid_to' => '2026-12-31',
        ]);
        $this->assertDatabaseHas('agent_tier_rates', [
            'rate_category' => AgentRateCategory::B->value,
            'room_type_id' => $this->roomType->id,
            'nightly_rate' => '1200000.00',
        ]);
        $this->assertDatabaseHas('agent_tier_rates', [
            'rate_category' => AgentRateCategory::D->value,
            'room_type_id' => $this->roomType->id,
            'nightly_rate' => '1100000.00',
        ]);
        $this->assertDatabaseMissing('agent_tier_rates', [
            'rate_category' => AgentRateCategory::C->value,
            'room_type_id' => $this->roomType->id,
        ]);
    }

    public function test_bulk_store_validation_failure_returns_errors(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->from(route('admin.agent-tier-rates.index'))
            ->post(route('admin.agent-tier-rates.bulk-store'), [
                'valid_from' => '2026-12-31',
                'valid_to' => '2026-01-01',
                'rates' => [
                    [
                        'room_type_id' => $this->roomType->id,
                        'A' => -100,
                    ],
                ],
            ])
            ->assertRedirect(route('admin.agent-tier-rates.index'))
            ->assertSessionHasErrors(['valid_to', 'rates.0.A']);

        $this->assertDatabaseCount('agent_tier_rates', 0);
    }
}
