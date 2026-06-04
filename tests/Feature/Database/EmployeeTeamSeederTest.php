<?php

use App\Models\Employee;
use App\Models\Team;
use Database\Seeders\EmployeeTeamSeeder;
use Illuminate\Support\Facades\DB;

it('backfills the pivot from home team and leader after seeding', function () {
    $team = Team::factory()->create();
    $member = Employee::factory()->create(['team_id' => $team->id]);
    $leader = Employee::factory()->create(['team_id' => $team->id]);
    $team->update(['leader_id' => $leader->id]);

    // Simulate the fresh-install state: pivot empty even though data exists.
    DB::table('employee_team')->truncate();

    (new EmployeeTeamSeeder)->run();

    expect($member->fresh()->teams()->where('teams.id', $team->id)->exists())->toBeTrue();

    // The leader gets a leader-role row (also a member via home team).
    $leaderPivot = DB::table('employee_team')
        ->where('employee_id', $leader->id)
        ->where('team_id', $team->id)
        ->first();
    expect($leaderPivot->role)->toBe('leader');
});

it('is idempotent on re-run', function () {
    $team = Team::factory()->create();
    Employee::factory()->create(['team_id' => $team->id]);

    DB::table('employee_team')->truncate();

    (new EmployeeTeamSeeder)->run();
    $countAfterFirst = DB::table('employee_team')->count();

    (new EmployeeTeamSeeder)->run();

    expect(DB::table('employee_team')->count())->toBe($countAfterFirst);
});
