<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill pivot rows for every employee with a home team (is_primary member).
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

        // Upsert leader rows for teams with a designated leader.
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

    /**
     * No-op: leave pivot rows as-is rather than risk deleting hand-edited data.
     * To fully reverse, drop the employee_team table via its own migration.
     */
    public function down(): void
    {
        // Intentionally left empty.
    }
};
