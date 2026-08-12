<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Hotel;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FbAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $admin;

    private User $housekeeper;

    private User $kitchenStaff;

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

        $this->housekeeper = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->housekeeper->assignRole('housekeeping');
        $this->hotel->users()->attach($this->housekeeper->id);

        $this->kitchenStaff = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->kitchenStaff->givePermissionTo(['fb.view', 'fb.orders.update_status']);
        $this->hotel->users()->attach($this->kitchenStaff->id);
    }

    public function test_fb_menu_requires_authentication(): void
    {
        $this->get('/fb/menu')->assertRedirect('/login');
    }

    public function test_fb_menu_accessible_by_admin(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/fb/menu')
            ->assertOk();
    }

    public function test_fb_menu_denied_without_permission(): void
    {
        $this->actingAs($this->housekeeper)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/fb/menu')
            ->assertForbidden();
    }

    public function test_kitchen_staff_can_access_kds(): void
    {
        $this->actingAs($this->kitchenStaff)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/fb/kds')
            ->assertOk();
    }

    public function test_kitchen_staff_can_update_item_status_to_preparing(): void
    {
        $orderItem = $this->createOrderItem();

        $this->actingAs($this->kitchenStaff)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->put("/fb/orders/{$orderItem->order_id}/items/{$orderItem->id}/status", [
                'status' => OrderItemStatus::Preparing->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'status' => OrderItemStatus::Preparing->value,
        ]);
    }

    public function test_kitchen_staff_cannot_mark_item_as_served(): void
    {
        $orderItem = $this->createOrderItem(OrderItemStatus::Ready);

        $this->actingAs($this->kitchenStaff)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->put("/fb/orders/{$orderItem->order_id}/items/{$orderItem->id}/status", [
                'status' => OrderItemStatus::Served->value,
            ])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('order_items', [
            'id' => $orderItem->id,
            'status' => OrderItemStatus::Ready->value,
        ]);
    }

    public function test_kitchen_staff_cannot_update_order_status(): void
    {
        $orderItem = $this->createOrderItem();

        $this->actingAs($this->kitchenStaff)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->put("/fb/orders/{$orderItem->order_id}/status", [
                'status' => OrderStatus::Served->value,
            ])
            ->assertForbidden();
    }

    public function test_housekeeper_cannot_update_item_status(): void
    {
        $orderItem = $this->createOrderItem();

        $this->actingAs($this->housekeeper)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->put("/fb/orders/{$orderItem->order_id}/items/{$orderItem->id}/status", [
                'status' => OrderItemStatus::Preparing->value,
            ])
            ->assertForbidden();
    }

    private function createOrderItem(OrderItemStatus $status = OrderItemStatus::New): OrderItem
    {
        $category = MenuCategory::query()->create([
            'name' => 'Mains',
            'sort_order' => 1,
        ]);

        $menuItem = MenuItem::query()->create([
            'menu_category_id' => $category->id,
            'name' => 'Nasi Goreng',
            'price' => 75000,
            'is_available' => true,
        ]);

        $order = Order::query()->create([
            'hotel_id' => $this->hotel->id,
            'order_no' => 'ORD-TEST-0001',
            'order_type' => OrderType::DineIn->value,
            'status' => OrderStatus::New->value,
            'opened_by' => $this->admin->id,
            'total_amount' => 75000,
        ]);

        return OrderItem::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
            'unit_price' => 75000,
            'status' => $status->value,
        ]);
    }
}
