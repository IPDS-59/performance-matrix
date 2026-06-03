<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Backfill the employee_team pivot from the denormalised columns
 * (employees.team_id home team + teams.leader_id).
 *
 * The same logic ships as the 2026_06_05_000600 migration, but on a fresh
 * install that migration runs before any employees/teams are seeded, so this
 * seeder re-applies it after seeding. Idempotent: safe to re-run.
 */
class EmployeeTeamSeeder extends Seeder
{
    public function run(): void
    {
        // Home team → is_primary member row.
        $employees = DB::table('employees')
            ->whereNotNull('team_id')
            ->select('id', 'team_id')
            ->get();

        foreach ($employees as $employee) {
            $exists = DB::table('employee_team')
                ->where('employee_id', $employee->id)
                ->where('team_id', $employee->team_id)
                ->exists();

            if (! $exists) {
                DB::table('employee_team')->insert([
                    'employee_id' => $employee->id,
                    'team_id' => $employee->team_id,
                    'role' => 'member',
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Designated leaders → upsert leader role.
        $teams = DB::table('teams')
            ->whereNotNull('leader_id')
            ->select('id', 'leader_id')
            ->get();

        foreach ($teams as $team) {
            $exists = DB::table('employee_team')
                ->where('employee_id', $team->leader_id)
                ->where('team_id', $team->id)
                ->exists();

            if ($exists) {
                DB::table('employee_team')
                    ->where('employee_id', $team->leader_id)
                    ->where('team_id', $team->id)
                    ->update([
                        'role' => 'leader',
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('employee_team')->insert([
                    'employee_id' => $team->leader_id,
                    'team_id' => $team->id,
                    'role' => 'leader',
                    'is_primary' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
