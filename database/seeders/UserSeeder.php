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

        // Kepala BPS Provinsi — linked to Imron Taufik J Musa
        $head = User::firstOrCreate(
            ['email' => 'kepala@bps-sulteng.go.id'],
            [
                'name' => 'Imron Taufik J Musa',
                'password' => Hash::make('password'),
                'role' => 'head',
            ]
        );
        $head->syncRoles('head');

        $kepalaEmployee = Employee::where('name', 'Imron Taufik J Musa')->first();
        if ($kepalaEmployee && ! $kepalaEmployee->user_id) {
            $kepalaEmployee->update([
                'user_id' => $head->id,
                'position' => 'Kepala BPS Provinsi Sulawesi Tengah',
            ]);
            $head->update(['name' => $kepalaEmployee->display_name ?? $kepalaEmployee->name]);
        }

        // Ketua Tim — linked to Hespri Yomeldi
        $hespri = User::firstOrCreate(
            ['email' => 'hespri@bps-sulteng.go.id'],
            [
                'name' => 'Hespri Yomeldi',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ]
        );
        $hespri->syncRoles('staff');

        $hespriEmployee = Employee::where('name', 'Hespri Yomeldi')->first();
        if ($hespriEmployee && ! $hespriEmployee->user_id) {
            $hespriEmployee->update(['user_id' => $hespri->id]);
            $hespri->update(['name' => $hespriEmployee->display_name ?? $hespriEmployee->name]);
        }

        // Staff demo — linked to Sukma Nirmala Dewi
        $staff = User::firstOrCreate(
            ['email' => 'staff@bps-sulteng.go.id'],
            [
                'name' => 'Staff Demo',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ]
        );
        $staff->syncRoles('staff');

        $staffEmployee = Employee::where('name', 'Sukma Nirmala Dewi')->first();
        if ($staffEmployee && ! $staffEmployee->user_id) {
            $staffEmployee->update(['user_id' => $staff->id]);
            $staff->update(['name' => $staffEmployee->display_name ?? $staffEmployee->name]);
        }
    }
}
