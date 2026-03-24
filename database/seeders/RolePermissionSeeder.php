<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionNames = [
            'admin.access',
            'users.manage',
            'roles.manage',
            'shipments.create',
            'shipments.view',
            'shipments.update',
            'shipments.delete',
            'invoices.view',
            'invoices.manage',
            'payments.manage',
            'documents.manage',
            'wallets.view',
        ];

        foreach ($permissionNames as $name) {
            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
            );
        }

        $superAdmin = Role::query()->firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => 'web'],
        );
        $superAdmin->syncPermissions(Permission::query()->where('guard_name', 'web')->get());

        $staffAdmin = Role::query()->firstOrCreate(
            ['name' => 'staff_admin', 'guard_name' => 'web'],
        );
        $staffAdmin->syncPermissions([
            'admin.access',
            'shipments.create',
            'shipments.view',
            'shipments.update',
            'shipments.delete',
            'invoices.view',
            'invoices.manage',
            'payments.manage',
            'documents.manage',
            'wallets.view',
        ]);

        $staffOperator = Role::query()->firstOrCreate(
            ['name' => 'staff_operator', 'guard_name' => 'web'],
        );
        $staffOperator->syncPermissions([
            'shipments.create',
            'shipments.view',
            'shipments.update',
            'documents.manage',
        ]);

        $shipperOwner = Role::query()->firstOrCreate(
            ['name' => 'shipper_owner', 'guard_name' => 'web'],
        );
        $shipperOwner->syncPermissions([
            'shipments.view',
            'invoices.view',
            'payments.manage',
            'documents.manage',
            'wallets.view',
        ]);
    }
}
