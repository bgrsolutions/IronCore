<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            'companies',
            'users',
            'customers',
            'suppliers',
            'products',
            'product_companies',
            'warehouses',
            'locations',
            'store_locations',
            'stock_moves',
            'documents',
            'vendor_bills',
            'expenses',
            'repairs',
            'sales_documents',
            'subscriptions',
            'subscription_plans',
            'subscription_runs',
            'purchase_plans',
            'accountant_exports',
            'verifactu_exports',
            'audit_logs',
            'tags',
        ];

        $abilities = ['viewAny', 'view', 'create', 'update', 'delete'];

        $permissionNames = [];
        foreach ($resources as $resource) {
            foreach ($abilities as $ability) {
                $permissionNames[] = sprintf('%s %s', $ability, $resource);
            }
        }

        foreach ($permissionNames as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->syncPermissions(Permission::query()->pluck('name')->all());

        User::role('admin')->get()->each(fn (User $user) => $user->syncPermissions([]));
    }
}
