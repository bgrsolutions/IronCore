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
            'accountant_export_batches',
            'audit_logs',
            'companies',
            'customers',
            'documents',
            'expenses',
            'locations',
            'product_companies',
            'products',
            'purchase_plans',
            'repairs',
            'sales_documents',
            'stock_moves',
            'store_locations',
            'subscription_plans',
            'subscription_runs',
            'subscriptions',
            'suppliers',
            'tags',
            'users',
            'vendor_bills',
            'verifactu_exports',
            'warehouses',
        ];

        $actions = ['view_any', 'view', 'create', 'update', 'delete'];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::findOrCreate("{$resource}.{$action}", 'web');
            }
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::query()->pluck('name'));

        $adminUser = User::query()->where('email', 'admin@ironcore.local')->first();
        if ($adminUser !== null && ! $adminUser->hasRole($adminRole)) {
            $adminUser->assignRole($adminRole);
        }
    }
}
