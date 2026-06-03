<?php

use App\Models\Employee;
use App\Models\Team;

it('belongs to many teams via pivot', function () {
    $employee = Employee::factory()->create();
    $teams = Team::factory()->count(2)->create();

    $employee->teams()->attach($teams[0]->id, ['role' => 'member', 'is_primary' => true]);
    $employee->teams()->attach($teams[1]->id, ['role' => 'leader', 'is_primary' => false]);

    expect($employee->teams)->toHaveCount(2);
});

it('pivot includes role and is_primary', function () {
    $employee = Employee::factory()->create();
    $team = Team::factory()->create();

    $employee->teams()->attach($team->id, ['role' => 'leader', 'is_primary' => true]);

    $pivot = $employee->teams->first()->pivot;

    expect($pivot->role)->toBe('leader');
    expect((bool) $pivot->is_primary)->toBeTrue();
});

it('ledTeams returns only teams where role is leader', function () {
    $employee = Employee::factory()->create();
    $memberTeam = Team::factory()->create();
    $leaderTeam = Team::factory()->create();

    $employee->teams()->attach($memberTeam->id, ['role' => 'member', 'is_primary' => true]);
    $employee->teams()->attach($leaderTeam->id, ['role' => 'leader', 'is_primary' => false]);

    $led = $employee->ledTeams()->get();

    expect($led)->toHaveCount(1);
    expect($led->first()->id)->toBe($leaderTeam->id);
});

it('team members pivot includes role', function () {
    $team = Team::factory()->create();
    $employees = Employee::factory()->count(3)->create();

    $team->members()->attach($employees[0]->id, ['role' => 'leader', 'is_primary' => false]);
    $team->members()->attach($employees[1]->id, ['role' => 'member', 'is_primary' => false]);
    $team->members()->attach($employees[2]->id, ['role' => 'member', 'is_primary' => false]);

    expect($team->members)->toHaveCount(3);
    expect($team->members->first()->pivot->role)->toBe('leader');
});
