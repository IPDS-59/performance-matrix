<?php

use App\Models\Employee;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

// ── GET /teams/{team}/members ─────────────────────────────────────────────

it('admin can access member management page', function () {
    $team = Team::factory()->create();

    $this->actingAs(adminUser())
        ->get(route('teams.members.edit', $team))
        ->assertInertia(fn ($page) => $page
            ->component('Teams/Members')
            ->has('team')
            ->has('employees')
        );
});

it('team lead can access their own team member management page', function () {
    $user = staffUser();
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    $team->update(['leader_id' => $employee->id]);

    $this->actingAs($user)
        ->get(route('teams.members.edit', $team))
        ->assertInertia(fn ($page) => $page->component('Teams/Members'));
});

it('team lead cannot access another team member management page', function () {
    $user = staffUser();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    $teamA->update(['leader_id' => $employee->id]);

    $this->actingAs($user)
        ->get(route('teams.members.edit', $teamB))
        ->assertForbidden();
});

it('plain staff member cannot access member management page', function () {
    $team = Team::factory()->create();

    $this->actingAs(staffUser())
        ->get(route('teams.members.edit', $team))
        ->assertForbidden();
});

// ── PUT /teams/{team}/members ─────────────────────────────────────────────

it('admin can add and set roles for team members', function () {
    $team = Team::factory()->create();
    $empA = Employee::factory()->create();
    $empB = Employee::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $empA->id, 'role' => 'leader', 'is_primary' => false],
                ['employee_id' => $empB->id, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertRedirect(route('teams.index'));

    expect($team->members()->count())->toBe(2);
    expect($team->members()->where('employees.id', $empA->id)->wherePivot('role', 'leader')->exists())->toBeTrue();
    expect($team->members()->where('employees.id', $empB->id)->wherePivot('role', 'member')->exists())->toBeTrue();
});

it('admin can remove a team member', function () {
    $team = Team::factory()->create();
    $empA = Employee::factory()->create();
    $empB = Employee::factory()->create();

    $team->members()->attach($empA->id, ['role' => 'member', 'is_primary' => false]);
    $team->members()->attach($empB->id, ['role' => 'member', 'is_primary' => false]);

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $empA->id, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertRedirect(route('teams.index'));

    expect($team->members()->count())->toBe(1);
    expect($team->members()->where('employees.id', $empB->id)->exists())->toBeFalse();
});

it('pivot reflects is_primary correctly', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $emp->id, 'role' => 'member', 'is_primary' => true],
            ],
        ])
        ->assertRedirect();

    $pivot = DB::table('employee_team')
        ->where('team_id', $team->id)
        ->where('employee_id', $emp->id)
        ->first();

    expect((bool) $pivot->is_primary)->toBeTrue();
});

it('team lead can manage members of their own team', function () {
    $user = staffUser();
    $team = Team::factory()->create();
    $leader = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    $team->update(['leader_id' => $leader->id]);

    $member = Employee::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $leader->id, 'role' => 'leader', 'is_primary' => false],
                ['employee_id' => $member->id, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertRedirect(route('teams.index'));

    expect($team->members()->count())->toBe(2);
});

it('team lead gets 403 when managing another team', function () {
    $user = staffUser();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $leader = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    $teamA->update(['leader_id' => $leader->id]);

    $member = Employee::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->put(route('teams.members.update', $teamB), [
            'members' => [
                ['employee_id' => $member->id, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertForbidden();
});

it('plain staff member is forbidden from updating team members', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();

    $this->actingAs(staffUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $emp->id, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertForbidden();
});

it('rejects invalid role value', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $emp->id, 'role' => 'superstar', 'is_primary' => false],
            ],
        ])
        ->assertSessionHasErrors(['members.0.role']);
});

it('rejects non-existent employee_id', function () {
    $team = Team::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => 999999, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertSessionHasErrors(['members.0.employee_id']);
});

it('idempotent sync does not duplicate rows', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();

    $team->members()->attach($emp->id, [
        'role' => 'member',
        'is_primary' => false,
        'started_at' => '2025-03-01',
    ]);

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $emp->id, 'role' => 'member', 'is_primary' => false],
            ],
        ])
        ->assertRedirect();

    expect($team->members()->count())->toBe(1);
});

it('preserves started_at on idempotent re-sync', function () {
    $team = Team::factory()->create();
    $emp = Employee::factory()->create();
    $originalDate = '2025-03-01';

    $team->members()->attach($emp->id, [
        'role' => 'member',
        'is_primary' => false,
        'started_at' => $originalDate,
    ]);

    $this->actingAs(adminUser())
        ->put(route('teams.members.update', $team), [
            'members' => [
                ['employee_id' => $emp->id, 'role' => 'leader', 'is_primary' => false],
            ],
        ])
        ->assertRedirect();

    $pivot = DB::table('employee_team')
        ->where('team_id', $team->id)
        ->where('employee_id', $emp->id)
        ->first();

    expect($pivot->started_at)->toBe($originalDate);
});
