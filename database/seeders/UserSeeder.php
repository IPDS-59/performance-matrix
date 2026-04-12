<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /** Derive email from employee name: "Sukma Nirmala Dewi" → "sukma@bpssulteng.id" */
    private function emailFromName(string $name): string
    {
        $firstName = Str::lower(Str::before($name, ' '));

        return "{$firstName}@bpssulteng.id";
    }

    private function linkEmployee(User $user, string $employeeName, ?array $extraUpdate = null): void
    {
        $employee = Employee::where('name', $employeeName)->first();
        if ($employee && ! $employee->user_id) {
            $employee->update(array_merge(['user_id' => $user->id], $extraUpdate ?? []));
            $user->update(['name' => $employee->display_name ?? $employee->name]);
        }
    }

    public function run(): void
    {
        // Admin — no linked employee
        $admin = User::firstOrCreate(
            ['email' => 'admin@bpssulteng.id'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
        $admin->syncRoles('admin');

        // Kepala BPS Provinsi — imron@bpssulteng.id → Imron Taufik J Musa
        $head = User::firstOrCreate(
            ['email' => $this->emailFromName('Imron Taufik J Musa')],
            [
                'name' => 'Imron Taufik J Musa',
                'password' => Hash::make('password'),
                'role' => 'head',
            ]
        );
        $head->syncRoles('head');
        $this->linkEmployee($head, 'Imron Taufik J Musa', [
            'position' => 'Kepala BPS Provinsi Sulawesi Tengah',
        ]);

        // Ketua Tim — hespri@bpssulteng.id → Hespri Yomeldi
        $hespri = User::firstOrCreate(
            ['email' => $this->emailFromName('Hespri Yomeldi')],
            [
                'name' => 'Hespri Yomeldi',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ]
        );
        $hespri->syncRoles('staff');
        $this->linkEmployee($hespri, 'Hespri Yomeldi');

        // Staff demo — sukma@bpssulteng.id → Sukma Nirmala Dewi
        $staff = User::firstOrCreate(
            ['email' => $this->emailFromName('Sukma Nirmala Dewi')],
            [
                'name' => 'Sukma Nirmala Dewi',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ]
        );
        $staff->syncRoles('staff');
        $this->linkEmployee($staff, 'Sukma Nirmala Dewi');
    }
}
