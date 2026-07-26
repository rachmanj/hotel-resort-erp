<?php

namespace Database\Seeders;

use App\Enums\InventoryCategory;
use App\Enums\InventoryUnit;
use App\Models\Hotel;
use App\Models\InventoryItem;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::query()->where('code', 'GNB')->first();

        if ($hotel === null) {
            return;
        }

        $items = [
            ['name' => 'Bath Towel', 'category' => InventoryCategory::Linen, 'unit' => InventoryUnit::Pcs, 'current_stock' => 120, 'reorder_level' => 50],
            ['name' => 'Hand Towel', 'category' => InventoryCategory::Linen, 'unit' => InventoryUnit::Pcs, 'current_stock' => 200, 'reorder_level' => 80],
            ['name' => 'Shampoo Sachet', 'category' => InventoryCategory::Amenity, 'unit' => InventoryUnit::Pcs, 'current_stock' => 45, 'reorder_level' => 100],
            ['name' => 'Toilet Paper Roll', 'category' => InventoryCategory::Amenity, 'unit' => InventoryUnit::Pcs, 'current_stock' => 30, 'reorder_level' => 60],
            ['name' => 'Coffee Beans', 'category' => InventoryCategory::FbIngredient, 'unit' => InventoryUnit::Kg, 'current_stock' => 8, 'reorder_level' => 5],
            ['name' => 'Cooking Oil', 'category' => InventoryCategory::FbIngredient, 'unit' => InventoryUnit::Ltr, 'current_stock' => 12, 'reorder_level' => 10],
            ['name' => 'AC Filter', 'category' => InventoryCategory::SparePart, 'unit' => InventoryUnit::Pcs, 'current_stock' => 3, 'reorder_level' => 5],
            ['name' => 'Light Bulb LED', 'category' => InventoryCategory::SparePart, 'unit' => InventoryUnit::Box, 'current_stock' => 2, 'reorder_level' => 4],
        ];

        foreach ($items as $item) {
            InventoryItem::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'name' => $item['name']],
                [
                    'category' => $item['category']->value,
                    'unit' => $item['unit']->value,
                    'current_stock' => $item['current_stock'],
                    'reorder_level' => $item['reorder_level'],
                    'location_type' => 'warehouse',
                ],
            );
        }

        $suppliers = [
            [
                'name' => 'PT Linen Nusantara',
                'contact_person' => 'Budi Santoso',
                'phone' => '+6281234567890',
                'email' => 'sales@linennusantara.co.id',
                'address' => 'Jl. Industri No. 12, Jakarta',
            ],
            [
                'name' => 'CV Amenities Bali',
                'contact_person' => 'Made Wijaya',
                'phone' => '+6289876543210',
                'email' => 'order@amenitiesbali.co.id',
                'address' => 'Jl. Raya Denpasar, Bali',
            ],
            [
                'name' => 'PT F&B Supply Co',
                'contact_person' => 'Siti Rahayu',
                'phone' => '+6281122334455',
                'email' => 'procurement@fbsupply.co.id',
                'address' => 'Jl. Pasar Baru No. 5, Surabaya',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'name' => $supplier['name']],
                [
                    ...$supplier,
                    'is_active' => true,
                ],
            );
        }
    }
}
