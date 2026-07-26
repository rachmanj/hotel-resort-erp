<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = MenuCategory::query()
            ->with(['items' => fn ($q) => $q->orderBy('name')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MenuCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'sort_order' => $category->sort_order,
                'items' => $category->items->map(fn (MenuItem $item) => [
                    'id' => $item->id,
                    'menu_category_id' => $item->menu_category_id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'price' => (float) $item->price,
                    'is_available' => $item->is_available,
                    'image_path' => $item->image_path,
                ]),
            ]);

        return Inertia::render('FB/Menu/Index', [
            'categories' => $categories,
            'categoryOptions' => MenuCategory::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(StoreMenuItemRequest $request): RedirectResponse
    {
        MenuItem::query()->create($request->validated());

        return back()->with('success', 'Menu item created.');
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem): RedirectResponse
    {
        $menuItem->update($request->validated());

        return back()->with('success', 'Menu item updated.');
    }

    public function destroy(MenuItem $menuItem): RedirectResponse
    {
        $menuItem->delete();

        return back()->with('success', 'Menu item deleted.');
    }

    public function toggleAvailability(MenuItem $menuItem): RedirectResponse
    {
        $menuItem->update(['is_available' => ! $menuItem->is_available]);

        return back()->with('success', 'Availability updated.');
    }
}
