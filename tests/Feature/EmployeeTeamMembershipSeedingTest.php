<?php

use App\Models\Employee;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

it('inserts a member+is_primary pivot row for each employee with a home team', function () {
    $team = Team::factory()->create(['leader_id' => null]);
    $employee = Employee::factory()->create(['team_id' => $team->id]);

    // Simulate the seeding migration logic directly (idempotent).
    $employees = DB::table('employees')->whereNotNull('team_id')->select('id', 'team_id')->get();
    foreach ($employees as $e) {
        $exists = DB::table('employee_team')
            ->where('employee_id', $e->id)
            ->where('team_id', $e->team_id)
            ->exists();
        if (! $exists) {
            DB::table('employee_team')->insert([
                'employee_id' => $e->id,
                'team_id' => $e->team_id,
                'role' => 'member',
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    $pivot = DB::table('employee_team')
        ->where('employee_id', $employee->id)
        ->where('team_id', $team->id)
        ->first();

    expect($pivot)->not->toBeNull();
    expect($pivot->role)->toBe('member');
    expect((bool) $pivot->is_primary)->toBeTrue();
});

it('inserts a leader pivot row for each team with a designated leader', function () {
    $leader = Employee::factory()->create(['team_id' => null]);
    $team = Team::factory()->create(['leader_id' => $leader->id]);

    $teams = DB::table('teams')->whereNotNull('leader_id')->select('id', 'leader_id')->get();
    foreach ($teams as $t) {
        $exists = DB::table('employee_team')
            ->where('employee_id', $t->leader_id)
            ->where('team_id', $t->id)
            ->exists();
        if ($exists) {
            DB::table('employee_team')
                ->where('employee_id', $t->leader_id)
                ->where('team_id', $t->id)
                ->update(['role' => 'leader', 'updated_at' => now()]);
        } else {
            DB::table('employee_team')->insert([
                'employee_id' => $t->leader_id,
                'team_id' => $t->id,
                'role' => 'leader',
                'is_primary' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    $pivot = DB::table('employee_team')
        ->where('employee_id', $leader->id)
        ->where('team_id', $team->id)
        ->first();

    expect($pivot)->not->toBeNull();
    expect($pivot->role)->toBe('leader');
});

it('upgrades an existing member row to leader when employee is also the team leader', function () {
    $leader = Employee::factory()->create();
    $team = Team::factory()->create(['leader_id' => $leader->id]);

    // Pre-insert as member (as if the home-team pass ran first).
    DB::table('employee_team')->insert([
        'employee_id' => $leader->id,
        'team_id' => $team->id,
        'role' => 'member',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Leader pass.
    $teams = DB::table('teams')->whereNotNull('leader_id')->select('id', 'leader_id')->get();
    foreach ($teams as $t) {
        $exists = DB::table('employee_team')
            ->where('employee_id', $t->leader_id)
            ->where('team_id', $t->id)
            ->exists();
        if ($exists) {
            DB::table('employee_team')
                ->where('employee_id', $t->leader_id)
                ->where('team_id', $t->id)
                ->update(['role' => 'leader', 'updated_at' => now()]);
        } else {
            DB::table('employee_team')->insert([
                'employee_id' => $t->leader_id,
                'team_id' => $t->id,
                'role' => 'leader',
                'is_primary' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    $pivot = DB::table('employee_team')
        ->where('employee_id', $leader->id)
        ->where('team_id', $team->id)
        ->first();

    expect($pivot->role)->toBe('leader');
    // is_primary is preserved from the earlier insert.
    expect((bool) $pivot->is_primary)->toBeTrue();
});

it('does not insert duplicate pivot rows when run twice', function () {
    $team = Team::factory()->create(['leader_id' => null]);
    $employee = Employee::factory()->create(['team_id' => $team->id]);

    $runPass = function () {
        $employees = DB::table('employees')->whereNotNull('team_id')->select('id', 'team_id')->get();
        foreach ($employees as $e) {
            if (! DB::table('employee_team')->where('employee_id', $e->id)->where('team_id', $e->team_id)->exists()) {
                DB::table('employee_team')->insert([
                    'employee_id' => $e->id,
                    'team_id' => $e->team_id,
                    'role' => 'member',
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    };

    $runPass();
    $runPass();

    expect(
        DB::table('employee_team')
            ->where('employee_id', $employee->id)
            ->where('team_id', $team->id)
            ->count()
    )->toBe(1);
});
