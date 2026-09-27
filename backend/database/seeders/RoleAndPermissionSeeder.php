<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the RBAC foundation: the granular `resource.action` permissions
 * from spec section 6, plus the 17 default roles from spec section 6,
 * each with a starting permission set. Only permissions for resources
 * that exist today (stores, warehouses, users, roles) are enforced by
 * any controller right now; the rest are latent, matching the spec's
 * own permission examples, ready for the phases that implement them.
 */
class RoleAndPermissionSeeder extends Seeder
{
    /** @var array<int, string> */
    private array $permissions = [
        // Foundation (enforced today)
        'stores.view', 'stores.create', 'stores.update', 'stores.delete',
        'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
        'users.view', 'users.create', 'users.update', 'users.delete',
        'roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign',
        'settings.manage',

        // Catalog / inventory / orders / customers (spec section 6 examples — latent until their phases ship)
        'products.view', 'products.create', 'products.update', 'products.delete',
        'inventory.view', 'inventory.adjust', 'inventory.transfer',
        'orders.view', 'orders.create', 'orders.update', 'orders.cancel',
        'customers.view', 'customers.update',
        'pages.manage',
        'blog.manage',
        'seo.manage',
        'reports.view',
        'builder.view', 'builder.edit', 'builder.publish',
    ];

    /** @var array<string, array<int, string>|string> */
    private array $roles = [
        'Super Admin' => 'all',
        'Store Owner' => 'all',
        'Administrator' => [
            'stores.view', 'stores.update', 'warehouses.view', 'warehouses.create', 'warehouses.update',
            'users.view', 'users.create', 'users.update', 'roles.view', 'roles.assign', 'settings.manage',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'inventory.view', 'inventory.adjust', 'inventory.transfer',
            'orders.view', 'orders.create', 'orders.update', 'orders.cancel',
            'customers.view', 'customers.update', 'reports.view',
        ],
        'Inventory Manager' => [
            'warehouses.view', 'warehouses.create', 'warehouses.update',
            'inventory.view', 'inventory.adjust', 'inventory.transfer', 'products.view', 'reports.view',
        ],
        'Order Manager' => [
            'orders.view', 'orders.create', 'orders.update', 'orders.cancel', 'customers.view', 'reports.view',
        ],
        'Sales Manager' => [
            'orders.view', 'orders.update', 'customers.view', 'customers.update', 'reports.view',
        ],
        'Warehouse Staff' => [
            'warehouses.view', 'inventory.view', 'inventory.adjust',
        ],
        'Purchase Manager' => [
            'products.view', 'inventory.view', 'inventory.adjust', 'reports.view',
        ],
        'Accountant' => [
            'orders.view', 'reports.view', 'settings.manage',
        ],
        'Marketing Manager' => [
            'products.view', 'blog.manage', 'reports.view', 'builder.view', 'builder.edit',
        ],
        'SEO Manager' => [
            'seo.manage', 'pages.manage', 'blog.manage', 'reports.view',
        ],
        'Content Manager' => [
            'pages.manage', 'blog.manage', 'builder.view', 'builder.edit', 'builder.publish',
        ],
        'Customer Support' => [
            'orders.view', 'customers.view', 'customers.update',
        ],
        'Delivery Manager' => [
            'orders.view', 'orders.update', 'reports.view',
        ],
        'Viewer' => [
            'stores.view', 'warehouses.view', 'products.view', 'inventory.view',
            'orders.view', 'customers.view', 'reports.view',
        ],
    ];

    public function run(): void
    {
        foreach ($this->permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach ($this->roles as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions === 'all' ? $this->permissions : $permissions);
        }
    }
}
