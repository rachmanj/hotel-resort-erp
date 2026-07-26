<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Appetizers', 'sort_order' => 1, 'items' => [
                ['name' => 'Spring Rolls', 'description' => 'Crispy vegetable spring rolls with sweet chili sauce', 'price' => 45000],
                ['name' => 'Satay Ayam', 'description' => 'Grilled chicken skewers with peanut sauce', 'price' => 55000],
                ['name' => 'Gado-Gado', 'description' => 'Indonesian vegetable salad with peanut dressing', 'price' => 48000],
            ]],
            ['name' => 'Main Courses', 'sort_order' => 2, 'items' => [
                ['name' => 'Nasi Goreng', 'description' => 'Traditional Indonesian fried rice', 'price' => 75000],
                ['name' => 'Beef Rendang', 'description' => 'Slow-cooked beef in coconut and spices', 'price' => 125000],
                ['name' => 'Grilled Fish', 'description' => 'Fresh catch of the day, grilled with sambal', 'price' => 145000],
            ]],
            ['name' => 'Soups', 'sort_order' => 3, 'items' => [
                ['name' => 'Soto Ayam', 'description' => 'Chicken soup with turmeric and vermicelli', 'price' => 65000],
                ['name' => 'Tom Yum', 'description' => 'Spicy Thai-style seafood soup', 'price' => 85000],
                ['name' => 'Miso Soup', 'description' => 'Japanese miso with tofu and seaweed', 'price' => 35000],
            ]],
            ['name' => 'Beverages', 'sort_order' => 4, 'items' => [
                ['name' => 'Fresh Coconut', 'description' => 'Young coconut served chilled', 'price' => 35000],
                ['name' => 'Es Teh Manis', 'description' => 'Sweet iced tea', 'price' => 15000],
                ['name' => 'Fresh Juice', 'description' => 'Seasonal tropical fruit juice', 'price' => 45000],
            ]],
            ['name' => 'Desserts', 'sort_order' => 5, 'items' => [
                ['name' => 'Es Campur', 'description' => 'Mixed ice dessert with fruits and jelly', 'price' => 40000],
                ['name' => 'Pisang Goreng', 'description' => 'Fried banana with honey', 'price' => 30000],
                ['name' => 'Cendol', 'description' => 'Pandan jelly with coconut milk and palm sugar', 'price' => 35000],
            ]],
        ];

        foreach ($categories as $catData) {
            $items = $catData['items'];
            unset($catData['items']);

            $category = MenuCategory::query()->firstOrCreate(
                ['name' => $catData['name']],
                ['sort_order' => $catData['sort_order']],
            );

            foreach ($items as $item) {
                MenuItem::query()->firstOrCreate(
                    ['menu_category_id' => $category->id, 'name' => $item['name']],
                    [
                        'description' => $item['description'],
                        'price' => $item['price'],
                        'is_available' => true,
                    ],
                );
            }
        }
    }
}
