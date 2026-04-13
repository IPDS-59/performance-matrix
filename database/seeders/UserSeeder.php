<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Derive email from employee name: "Sukma Nirmala Dewi" → "sukma@bpssulteng.id"
     * Appends a counter to resolve collisions: "sukma2@bpssulteng.id", etc.
     *
     * @param  array<string>  $usedEmails
     */
    private function emailFromName(string $name, array &$usedEmails): string
    {
        $firstName = Str::lower(Str::before($name, ' '));
        $base = "{$firstName}@bpssulteng.id";

        if (! in_array($base, $usedEmails, true)) {
            $usedEmails[] = $base;

            return $base;
        }

        $counter = 2;
        do {
            $candidate = "{$firstName}{$counter}@bpssulteng.id";
            $counter++;
        } while (in_array($candidate, $usedEmails, true));

        $usedEmails[] = $candidate;

        return $candidate;
    }

    public function run(): void
    {
        // Track emails already allocated so we can handle first-name collisions.
        $usedEmails = ['admin@bpssulteng.id'];

        // ── Admin — no linked employee ────────────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@bpssulteng.id'],
            [
                'name'     => 'Administrator',
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );
        $admin->syncRoles('admin');

        // ── One user per employee ─────────────────────────────────────────────
        $employees = Employee::where('is_active', true)->orderBy('name')->get();

        foreach ($employees as $employee) {
            $email = $this->emailFromName($employee->name, $usedEmails);

            // Determine role: first employee in the list seeded as 'head', rest as 'staff'.
            // Override: if the employee already has a linked user, keep that role.
            $existingUser = $employee->user_id ? User::find($employee->user_id) : null;
            if ($existingUser) {
                // Already linked — just ensure the role is synced and email is tracked.
                if (! in_array($existingUser->email, $usedEmails, true)) {
                    $usedEmails[] = $existingUser->email;
                }
                continue;
            }

            $role = 'staff';

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'     => $employee->display_name ?? $employee->name,
                    'password' => Hash::make('password'),
                    'role'     => $role,
                ]
            );
            $user->syncRoles($role);

            // Link employee → user (write-through display_name cache)
            if (! $employee->user_id) {
                $employee->update(['user_id' => $user->id]);
                $user->update(['name' => $employee->display_name ?? $employee->name]);
            }
        }
    }
}
