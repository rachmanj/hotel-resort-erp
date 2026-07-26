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
            'telegram.link', 'profile.telegram.view',
            'rooms.view', 'rooms.manage',
            'reservations.view', 'reservations.create', 'reservations.edit', 'reservations.cancel', 'reservations.checkin', 'reservations.checkout',
            'housekeeping.view', 'housekeeping.manage', 'housekeeping.update_status',
            'maintenance.view', 'maintenance.manage', 'maintenance.create', 'maintenance.escalate',
            'fb.view', 'fb.manage',
            'inventory.view', 'purchasing.view', 'purchasing.approve',
            'billing.view', 'billing.manage', 'billing.post', 'billing.payment', 'billing.invoice',
            'folios.view',
            'guests.view', 'guests.manage', 'guests.create', 'guests.edit',
            'companies.view', 'companies.manage',
            'tax.manage',
            'reports.view', 'reports.fb_sales',
            'accounting.view', 'accounting.manage', 'accounting.post', 'accounting.approve',
            'admin.manage', 'hotels.manage', 'currencies.manage', 'floors.manage',
            'rates.manage', 'seasons.manage',
            'spa.view', 'spa.manage',
        ],
        'manager' => [
            'telegram.link', 'profile.telegram.view',
            'rooms.view',
            'reservations.view', 'reservations.cancel',
            'housekeeping.view',
            'maintenance.view', 'maintenance.escalate',
            'fb.view',
            'inventory.view', 'purchasing.view', 'purchasing.approve',
            'billing.view', 'billing.invoice',
            'folios.view',
            'guests.view',
            'companies.view',
            'reports.view',
            'accounting.view',
        ],
        'front_office' => [
            'telegram.link', 'profile.telegram.view',
            'rooms.view',
            'reservations.view', 'reservations.create', 'reservations.edit', 'reservations.cancel', 'reservations.checkin', 'reservations.checkout',
            'housekeeping.view', 'housekeeping.update_status',
            'maintenance.create',
            'fb.view',
            'billing.view', 'billing.post', 'billing.payment', 'billing.invoice',
            'folios.view',
            'guests.view', 'guests.create', 'guests.edit',
            'companies.view',
            'reports.view',
        ],
        'housekeeping' => [
            'telegram.link', 'profile.telegram.view',
            'rooms.view',
            'housekeeping.view', 'housekeeping.manage',
            'maintenance.create',
            'inventory.view',
            'guests.view',
        ],
        'fb' => [
            'telegram.link', 'profile.telegram.view',
            'fb.view', 'fb.manage',
            'inventory.view',
            'reports.fb_sales',
            'guests.view',
        ],
        'finance' => [
            'telegram.link', 'profile.telegram.view',
            'rooms.view',
            'reservations.view',
            'inventory.view', 'purchasing.view', 'purchasing.approve',
            'billing.view', 'billing.payment', 'billing.invoice',
            'folios.view',
            'guests.view',
            'companies.view', 'companies.manage',
            'tax.manage',
            'reports.view',
            'accounting.view', 'accounting.manage', 'accounting.post', 'accounting.approve',
        ],
        'maintenance' => [
            'telegram.link', 'profile.telegram.view',
            'maintenance.view', 'maintenance.manage',
            'accounting.view',
            'guests.view',
        ],
        'spa' => [
            'telegram.link', 'profile.telegram.view',
            'spa.view', 'spa.manage',
            'guests.view',
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
