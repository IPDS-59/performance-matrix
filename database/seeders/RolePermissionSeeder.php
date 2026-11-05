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
            'manage-kip-integration',
            'manage-personnel',
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
            'manage-kip-integration',
            'manage-personnel',
        ]);

        // SDM / kepegawaian: employees, teams and everything about angka kredit, without the rest of the admin tools.
        // An add-on role: the person keeps the staff role for their own work.
        Role::firstOrCreate(['name' => 'admin-kepegawaian'])->syncPermissions([
            'manage-employees',
            'manage-teams',
            'manage-personnel',
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
