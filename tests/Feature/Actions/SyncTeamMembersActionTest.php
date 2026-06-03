<?php

use App\Actions\Teams\SyncTeamMembersAction;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

it('attaches new members with started_at set to today', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();

    $action = new SyncTeamMembersAction;
    $action->execute($team, [
        $emp->id => ['role' => 'member', 'is_primary' => false],
    ]);

    $pivot = DB::table('employee_team')
        ->where('team_id', $team->id)
        ->where('employee_id', $emp->id)
        ->first();

    expect($pivot)->not->toBeNull();
    expect($pivot->role)->toBe('member');
    expect($pivot->started_at)->toBe(now()->toDateString());
});

it('preserves started_at for existing members on re-sync', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();
    $originalDate = '2025-01-15';

    $team->members()->attach($emp->id, [
        'role' => 'member',
        'is_primary' => false,
        'started_at' => $originalDate,
    ]);

    $action = new SyncTeamMembersAction;
    $action->execute($team, [
        $emp->id => ['role' => 'leader', 'is_primary' => false],
    ]);

    $pivot = DB::table('employee_team')
        ->where('team_id', $team->id)
        ->where('employee_id', $emp->id)
        ->first();

    expect($pivot->started_at)->toBe($originalDate);
    expect($pivot->role)->toBe('leader');
});

it('removes members not in the new map', function () {
    $team = Team::factory()->create();
    $empA = Employee::factory()->create();
    $empB = Employee::factory()->create();

    $team->members()->attach($empA->id, ['role' => 'member', 'is_primary' => false]);
    $team->members()->attach($empB->id, ['role' => 'member', 'is_primary' => false]);

    $action = new SyncTeamMembersAction;
    $action->execute($team, [
        $empA->id => ['role' => 'member', 'is_primary' => false],
    ]);

    expect($team->members()->count())->toBe(1);
    expect($team->members()->where('employees.id', $empA->id)->exists())->toBeTrue();
    expect($team->members()->where('employees.id', $empB->id)->exists())->toBeFalse();
});

it('updates leader_id when exactly one leader submitted', function () {
    $team = Team::factory()->create(['leader_id' => null]);
    $emp = Employee::factory()->create();

    $action = new SyncTeamMembersAction;
    $action->execute($team, [
        $emp->id => ['role' => 'leader', 'is_primary' => false],
    ]);

    expect($team->fresh()->leader_id)->toBe($emp->id);
});

it('does not change leader_id when zero leaders submitted', function () {
    $team = Team::factory()->create();
    $leader = Employee::factory()->create();
    $team->update(['leader_id' => $leader->id]);

    $member = Employee::factory()->create();

    $action = new SyncTeamMembersAction;
    $action->execute($team, [
        $member->id => ['role' => 'member', 'is_primary' => false],
    ]);

    expect($team->fresh()->leader_id)->toBe($leader->id);
});
