<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage-teams',
            'manage-employees',
            'manage-projects',
            'manage-work-items',
            'create-project',
            'view-matrix',
            'view-reports',
            'enter-performance',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions([
            'manage-teams',
            'manage-employees',
            'manage-projects',
            'manage-work-items',
            'view-matrix',
            'view-reports',
        ]);

        $head = Role::firstOrCreate(['name' => 'head']);
        $head->syncPermissions([
            'view-matrix',
            'view-reports',
            'enter-performance',
        ]);

        $staff = Role::firstOrCreate(['name' => 'staff']);
        $staff->syncPermissions([
            'enter-performance',
        ]);
    }
}
