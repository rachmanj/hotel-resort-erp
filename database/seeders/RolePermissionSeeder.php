<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private array $rolePermissions = [
        'admin' => [
            'rooms.view', 'rooms.manage',
            'reservations.view', 'reservations.create', 'reservations.edit', 'reservations.cancel', 'reservations.checkin', 'reservations.checkout',
            'housekeeping.view', 'housekeeping.manage', 'housekeeping.update_status',
            'maintenance.view', 'maintenance.manage', 'maintenance.create', 'maintenance.escalate',
            'fb.view', 'fb.manage',
            'inventory.view', 'purchasing.view', 'purchasing.approve',
            'billing.view', 'billing.manage',
            'guests.view', 'guests.manage',
            'reports.view', 'reports.fb_sales',
            'accounting.view', 'accounting.manage', 'accounting.post', 'accounting.approve',
            'admin.manage', 'hotels.manage', 'currencies.manage', 'floors.manage',
            'spa.view', 'spa.manage',
        ],
        'manager' => [
            'rooms.view',
            'reservations.view', 'reservations.cancel',
            'housekeeping.view',
            'maintenance.view', 'maintenance.escalate',
            'fb.view',
            'inventory.view', 'purchasing.view', 'purchasing.approve',
            'billing.view',
            'guests.view',
            'reports.view',
            'accounting.view',
        ],
        'front_office' => [
            'rooms.view',
            'reservations.view', 'reservations.create', 'reservations.edit', 'reservations.cancel', 'reservations.checkin', 'reservations.checkout',
            'housekeeping.view', 'housekeeping.update_status',
            'maintenance.create',
            'fb.view',
            'reports.view',
        ],
        'housekeeping' => [
            'rooms.view',
            'housekeeping.view', 'housekeeping.manage',
            'maintenance.create',
            'inventory.view',
        ],
        'fb' => [
            'fb.view', 'fb.manage',
            'inventory.view',
            'reports.fb_sales',
        ],
        'finance' => [
            'rooms.view',
            'reservations.view',
            'inventory.view', 'purchasing.view', 'purchasing.approve',
            'billing.view',
            'reports.view',
            'accounting.view', 'accounting.manage', 'accounting.post', 'accounting.approve',
        ],
        'maintenance' => [
            'maintenance.view', 'maintenance.manage',
            'accounting.view',
        ],
        'spa' => [
            'spa.view', 'spa.manage',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = collect($this->rolePermissions)
            ->flatten()
            ->unique()
            ->values();

        foreach ($allPermissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach ($this->rolePermissions as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }
}
