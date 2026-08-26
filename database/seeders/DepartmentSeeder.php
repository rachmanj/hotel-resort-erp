<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const GLOBAL_DEPARTMENTS = [
        'kitchen' => 'Kitchen & Restaurant',
        'front_office' => 'Front Office',
        'housekeeping' => 'Housekeeping',
        'head_office' => 'Head Office',
        'engineering' => 'Engineering & Maintenance',
        'civil' => 'Civil / Construction',
        'bar' => 'Bar',
        'driver' => 'Driver / Transport',
        'marketing' => 'Marketing',
        'spa' => 'Spa & Wellness',
    ];

    public function run(): void
    {
        foreach (self::GLOBAL_DEPARTMENTS as $code => $name) {
            Department::query()
                ->withoutGlobalScope('hotel')
                ->updateOrCreate(
                    ['hotel_id' => null, 'code' => $code],
                    ['name' => $name, 'is_active' => true],
                );
        }
    }
}
