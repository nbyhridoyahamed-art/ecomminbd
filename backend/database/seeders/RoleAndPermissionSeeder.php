<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds the RBAC foundation: the granular `resource.action` permissions
 * from spec section 6, plus the 17 default roles from spec section 6,
 * each with a starting permission set. Permissions for resources that
 * exist today (stores, warehouses, users, roles, catalog, inventory,
 * purchasing, orders, delivery) are enforced by their controllers; the
 * rest are latent, matching the spec's own permission examples, ready
 * for the phases that implement them.
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

        // Catalog + Inventory + Purchasing + Orders (enforced today) — the rest of
        // spec section 6's examples remain latent until their phases ship.
        'products.view', 'products.create', 'products.update', 'products.delete',
        'categories.view', 'categories.create', 'categories.update', 'categories.delete',
        'brands.view', 'brands.create', 'brands.update', 'brands.delete',
        'attributes.view', 'attributes.create', 'attributes.update', 'attributes.delete',
        'inventory.view', 'inventory.adjust', 'inventory.transfer',
        'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
        'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update',
        'purchase_orders.cancel', 'purchase_orders.receive',
        'orders.view', 'orders.create', 'orders.update', 'orders.cancel',
        'customers.view', 'customers.create', 'customers.update', 'customers.delete',

        // Delivery (enforced today).
        'couriers.view', 'couriers.create', 'couriers.update', 'couriers.delete',
        'shipments.view', 'shipments.create', 'shipments.update',
        'cod_settlements.view', 'cod_settlements.create',

        // Returns (enforced today).
        'returns.view', 'returns.create', 'returns.update',

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
            'categories.view', 'categories.create', 'categories.update', 'categories.delete',
            'brands.view', 'brands.create', 'brands.update', 'brands.delete',
            'attributes.view', 'attributes.create', 'attributes.update', 'attributes.delete',
            'inventory.view', 'inventory.adjust', 'inventory.transfer',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update',
            'purchase_orders.cancel', 'purchase_orders.receive',
            'orders.view', 'orders.create', 'orders.update', 'orders.cancel',
            'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'reports.view',
            'couriers.view', 'couriers.create', 'couriers.update', 'couriers.delete',
            'shipments.view', 'shipments.create', 'shipments.update',
            'cod_settlements.view', 'cod_settlements.create',
            'returns.view', 'returns.create', 'returns.update',
        ],
        'Inventory Manager' => [
            'warehouses.view', 'warehouses.create', 'warehouses.update',
            'inventory.view', 'inventory.adjust', 'inventory.transfer',
            'products.view', 'categories.view', 'brands.view', 'attributes.view', 'reports.view',
        ],
        'Order Manager' => [
            'orders.view', 'orders.create', 'orders.update', 'orders.cancel',
            'customers.view', 'customers.create', 'customers.update', 'reports.view',
            'returns.view', 'returns.create', 'returns.update',
        ],
        'Sales Manager' => [
            'orders.view', 'orders.update', 'customers.view', 'customers.update', 'reports.view',
            'returns.view',
        ],
        'Warehouse Staff' => [
            'warehouses.view', 'inventory.view', 'inventory.adjust', 'purchase_orders.view', 'purchase_orders.receive',
            'returns.view', 'returns.update',
        ],
        'Purchase Manager' => [
            'products.view', 'categories.view', 'brands.view', 'attributes.view', 'inventory.view', 'inventory.adjust', 'reports.view',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
            'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update',
            'purchase_orders.cancel', 'purchase_orders.receive',
        ],
        'Accountant' => [
            'orders.view', 'reports.view', 'settings.manage',
            'couriers.view', 'cod_settlements.view', 'cod_settlements.create',
            'returns.view', 'returns.update',
        ],
        'Marketing Manager' => [
            'products.view', 'categories.view', 'categories.update', 'brands.view', 'brands.update',
            'attributes.view', 'attributes.update',
            'blog.manage', 'reports.view', 'builder.view', 'builder.edit',
        ],
        'SEO Manager' => [
            'seo.manage', 'pages.manage', 'blog.manage', 'reports.view',
        ],
        'Content Manager' => [
            'pages.manage', 'blog.manage', 'builder.view', 'builder.edit', 'builder.publish',
        ],
        'Customer Support' => [
            'orders.view', 'customers.view', 'customers.create', 'customers.update',
            'returns.view', 'returns.create',
        ],
        'Delivery Manager' => [
            'orders.view', 'orders.update', 'reports.view',
            'couriers.view', 'shipments.view', 'shipments.create', 'shipments.update',
            'cod_settlements.view', 'cod_settlements.create',
            'returns.view',
        ],
        'Viewer' => [
            'stores.view', 'warehouses.view', 'products.view', 'categories.view', 'brands.view', 'attributes.view',
            'inventory.view', 'suppliers.view', 'purchase_orders.view', 'orders.view', 'customers.view', 'reports.view',
            'couriers.view', 'shipments.view', 'cod_settlements.view', 'returns.view',
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
