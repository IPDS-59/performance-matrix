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
     * Derive email from employee name.
     *
     * If the employee's first name is shared with any other employee,
     * use first+second name: "Muhammad Andi" → "muhammadandi@bpssulteng.id".
     * Otherwise use first name only: "Sukma Nirmala Dewi" → "sukma@bpssulteng.id".
     * Appends a counter to resolve any remaining collisions.
     *
     * @param  array<string>  $duplicateFirstNames  first names that appear on >1 employee
     * @param  array<string>  $usedEmails
     */
    private function emailFromName(string $name, array $duplicateFirstNames, array &$usedEmails): string
    {
        $firstName = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::before($name, ' ')));
        $secondName = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::before(Str::after($name, ' '), ' ')));

        $localPart = in_array($firstName, $duplicateFirstNames, true)
            ? $firstName.$secondName
            : $firstName;

        $base = "{$localPart}@bpssulteng.id";

        if (! in_array($base, $usedEmails, true)) {
            $usedEmails[] = $base;

            return $base;
        }

        $counter = 2;
        do {
            $candidate = "{$localPart}{$counter}@bpssulteng.id";
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
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
        $admin->syncRoles('admin');

        // ── One user per employee ─────────────────────────────────────────────
        $employees = Employee::where('is_active', true)->orderBy('name')->get();

        // Pre-scan: find which first names are shared across multiple employees.
        // For those, we'll use first+second name in the email to avoid collisions.
        $firstNameCounts = [];
        foreach ($employees as $employee) {
            $first = preg_replace('/[^a-z0-9]/', '', Str::lower(Str::before($employee->name, ' ')));
            $firstNameCounts[$first] = ($firstNameCounts[$first] ?? 0) + 1;
        }
        $duplicateFirstNames = array_keys(array_filter($firstNameCounts, fn ($c) => $c > 1));

        foreach ($employees as $employee) {
            $email = $this->emailFromName($employee->name, $duplicateFirstNames, $usedEmails);

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
                    'name' => $employee->name,
                    'password' => Hash::make('password'),
                    'role' => $role,
                ]
            );
            $user->syncRoles($role);

            // Link employee → user
            if (! $employee->user_id) {
                $employee->update(['user_id' => $user->id]);
                $user->update(['name' => $employee->name]);
            }
        }
    }
}
