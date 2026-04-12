<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@bps-sulteng.go.id'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
        $admin->syncRoles('admin');

        $head = User::firstOrCreate(
            ['email' => 'kepala@bps-sulteng.go.id'],
            [
                'name' => 'Kepala BPS',
                'password' => Hash::make('password'),
                'role' => 'head',
            ]
        );
        $head->syncRoles('head');

        $staff = User::firstOrCreate(
            ['email' => 'staff@bps-sulteng.go.id'],
            [
                'name' => 'Staff Demo',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ]
        );
        $staff->syncRoles('staff');

        // Link staff demo to a seeded employee so performance entry works
        $employee = Employee::where('name', 'Sukma Nirmala Dewi')->first();
        if ($employee && ! $employee->user_id) {
            $employee->update(['user_id' => $staff->id]);
            $staff->update(['name' => $employee->display_name ?? $employee->name]);
        }
    }
}
