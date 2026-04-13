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

        // Kepala BPS Provinsi — andi@bpssulteng.id → Andi Kurniawan
        $head = User::firstOrCreate(
            ['email' => $this->emailFromName('Andi Kurniawan')],
            [
                'name' => 'Andi Kurniawan',
                'password' => Hash::make('password'),
                'role' => 'head',
            ]
        );
        $head->syncRoles('head');
        $this->linkEmployee($head, 'Andi Kurniawan', [
            'position' => 'Kepala BPS Provinsi Sulawesi Tengah',
        ]);

        // Ketua Tim — bagas@bpssulteng.id → Bagas Wicaksono
        $lead = User::firstOrCreate(
            ['email' => $this->emailFromName('Bagas Wicaksono')],
            [
                'name' => 'Bagas Wicaksono',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ]
        );
        $lead->syncRoles('staff');
        $this->linkEmployee($lead, 'Bagas Wicaksono');

        // Staff demo — citra@bpssulteng.id → Citra Dewanti
        $staff = User::firstOrCreate(
            ['email' => $this->emailFromName('Citra Dewanti')],
            [
                'name' => 'Citra Dewanti',
                'password' => Hash::make('password'),
                'role' => 'staff',
            ]
        );
        $staff->syncRoles('staff');
        $this->linkEmployee($staff, 'Citra Dewanti');
    }
}
