<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MenuCategoryActiveTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $user;

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

        $this->user = User::factory()->create(['hotel_id' => null]);
        $this->user->givePermissionTo(['fb.view', 'fb.manage', 'fb.orders.create']);
        $this->hotel->users()->attach($this->user->id);
    }

    public function test_inactive_category_is_excluded_from_order_create_props(): void
    {
        $inactiveCategory = MenuCategory::query()->create([
            'name' => 'Appetizers',
            'sort_order' => 1,
            'is_active' => false,
        ]);

        MenuItem::query()->create([
            'menu_category_id' => $inactiveCategory->id,
            'name' => 'Spring Rolls',
            'price' => 45000,
            'is_available' => true,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('fb.orders.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FB/Orders/Create')
                ->has('menuCategories', 0)
            );
    }

    public function test_active_category_with_available_items_is_included_in_order_create_props(): void
    {
        $activeCategory = MenuCategory::query()->create([
            'name' => 'Main Courses',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $menuItem = MenuItem::query()->create([
            'menu_category_id' => $activeCategory->id,
            'name' => 'Grilled Fish',
            'price' => 120000,
            'is_available' => true,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('fb.orders.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FB/Orders/Create')
                ->has('menuCategories', 1)
                ->where('menuCategories.0.id', $activeCategory->id)
                ->where('menuCategories.0.name', 'Main Courses')
                ->has('menuCategories.0.items', 1)
                ->where('menuCategories.0.items.0.id', $menuItem->id)
            );
    }

    public function test_active_category_with_no_available_items_is_excluded_from_order_create_props(): void
    {
        $activeCategory = MenuCategory::query()->create([
            'name' => 'Soups',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        MenuItem::query()->create([
            'menu_category_id' => $activeCategory->id,
            'name' => 'Tom Yum',
            'price' => 55000,
            'is_available' => false,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('fb.orders.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FB/Orders/Create')
                ->has('menuCategories', 0)
            );
    }

    public function test_toggle_category_active_endpoint_changes_flag(): void
    {
        $category = MenuCategory::query()->create([
            'name' => 'Beverages',
            'sort_order' => 4,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('fb.menu.categories.toggle', $category))
            ->assertRedirect();

        $this->assertDatabaseHas('menu_categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('fb.menu.categories.toggle', $category))
            ->assertRedirect();

        $this->assertDatabaseHas('menu_categories', [
            'id' => $category->id,
            'is_active' => true,
        ]);
    }
}
