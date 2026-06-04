<?php

use App\Models\Employee;
use App\Models\Project;
use App\Models\Team;

it('redirects guests to login', function () {
    $this->get(route('projects.index'))->assertRedirect(route('login'));
});

it('renders index for admin', function () {
    $this->actingAs(adminUser())
        ->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page->component('Projects/Index')->has('projects')->has('teams'));
});

it('allows index for staff', function () {
    $this->actingAs(staffUser())
        ->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page->component('Projects/Index')->has('projects')->has('teams'));
});

it('denies project creation for staff', function () {
    $this->actingAs(staffUser())
        ->get(route('projects.create'))
        ->assertForbidden();
});

it('renders create form', function () {
    $this->actingAs(adminUser())
        ->get(route('projects.create'))
        ->assertInertia(fn ($page) => $page->component('Projects/Create')->has('teams')->has('employees'));
});

it('stores a project and redirects to edit', function () {
    $team = Team::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('projects.store'), [
            'team_id' => $team->id,
            'name' => 'Sensus Penduduk',
            'year' => 2026,
            'status' => 'active',
            'members' => [],
        ])
        ->assertRedirect(route('projects.edit', Project::where('name', 'Sensus Penduduk')->firstOrFail()));

    expect(Project::where('name', 'Sensus Penduduk')->exists())->toBeTrue();
});

it('validates required fields on store', function () {
    $this->actingAs(adminUser())
        ->post(route('projects.store'), [])
        ->assertSessionHasErrors(['team_id', 'name', 'year']);
});

it('renders edit form for admin', function () {
    $project = Project::factory()->create();

    $this->actingAs(adminUser())
        ->get(route('projects.edit', $project))
        ->assertInertia(fn ($page) => $page->component('Projects/Edit')->has('project'));
});

it('updates a project and redirects', function () {
    $project = Project::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('projects.update', $project), [
            'team_id' => $project->team_id,
            'name' => 'Updated Project',
            'year' => $project->year,
            'status' => 'active',
            'members' => [],
        ])
        ->assertRedirect(route('projects.index'));

    expect($project->fresh()->name)->toBe('Updated Project');
});

it('deletes a project and redirects', function () {
    $project = Project::factory()->create();

    $this->actingAs(adminUser())
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    expect(Project::find($project->id))->toBeNull();
});

// ── Team lead tests (membership team ≠ led team) ──────────────────────────

it('team lead can access create form and sees only led teams and all active employees', function () {
    $user = staffUser();
    $teamA = Team::factory()->create(['is_active' => true]); // membership team
    $teamB = Team::factory()->create(['is_active' => true]); // led team
    $teamC = Team::factory()->create(['is_active' => true]); // unrelated team

    $employee = Employee::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id, 'is_active' => true]);
    $teamB->update(['leader_id' => $employee->id]);

    // Active employees in other teams
    Employee::factory()->count(3)->create(['team_id' => $teamC->id, 'is_active' => true]);

    $totalActive = Employee::where('is_active', true)->count();

    $this->actingAs($user)
        ->get(route('projects.create'))
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Create')
            ->where('isAdmin', false)
            ->has('teams', 1)
            ->where('teams.0.id', $teamB->id)
            ->has('employees', $totalActive)
        );
});

it('team lead can store a project for a led team with cross-team members', function () {
    $user = staffUser();
    $teamA = Team::factory()->create(['is_active' => true]);
    $teamB = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id, 'is_active' => true]);
    $teamB->update(['leader_id' => $employee->id]);

    $crossTeamEmployee = Employee::factory()->create(['team_id' => $teamA->id, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('projects.store'), [
            'team_id' => $teamB->id,
            'name' => 'Proyek Tim B',
            'year' => 2026,
            'leader_id' => $employee->id,
            'members' => [
                ['employee_id' => $employee->id, 'role' => 'leader'],
                ['employee_id' => $crossTeamEmployee->id, 'role' => 'member'],
            ],
        ])
        ->assertRedirect();

    $project = Project::where('name', 'Proyek Tim B')->firstOrFail();
    expect($project->team_id)->toBe($teamB->id);
    expect($project->members()->where('employees.id', $employee->id)->wherePivot('role', 'leader')->exists())->toBeTrue();
    expect($project->members()->where('employees.id', $crossTeamEmployee->id)->exists())->toBeTrue();
});

it('team lead cannot store a project for a team they do not lead', function () {
    $user = staffUser();
    $teamA = Team::factory()->create(['is_active' => true]);
    $teamB = Team::factory()->create(['is_active' => true]);
    $teamC = Team::factory()->create(['is_active' => true]); // not led by this employee
    $employee = Employee::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id, 'is_active' => true]);
    $teamB->update(['leader_id' => $employee->id]);

    $this->actingAs($user)
        ->post(route('projects.store'), [
            'team_id' => $teamC->id,
            'name' => 'Proyek Tidak Diizinkan',
            'year' => 2026,
            'members' => [],
        ])
        ->assertSessionHasErrors(['team_id']);
});

it('staff with no led team gets 403 on store', function () {
    $user = staffUser();
    $teamA = Team::factory()->create(['is_active' => true]);
    // Employee exists but leads no team
    Employee::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id, 'is_active' => true]);

    $this->actingAs($user)
        ->post(route('projects.store'), [
            'team_id' => $teamA->id,
            'name' => 'Proyek Tanpa Tim Pimpinan',
            'year' => 2026,
            'members' => [],
        ])
        ->assertForbidden();
});

it('leader_id defaults to the team lead when omitted', function () {
    $user = staffUser();
    $teamA = Team::factory()->create(['is_active' => true]);
    $teamB = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id, 'is_active' => true]);
    $teamB->update(['leader_id' => $employee->id]);

    $this->actingAs($user)
        ->post(route('projects.store'), [
            'team_id' => $teamB->id,
            'name' => 'Proyek Default Leader',
            'year' => 2026,
            'members' => [
                ['employee_id' => $employee->id, 'role' => 'leader'],
            ],
        ])
        ->assertRedirect();

    $project = Project::where('name', 'Proyek Default Leader')->firstOrFail();
    expect($project->leader_id)->toBe($employee->id);
    expect($project->members()->where('employees.id', $employee->id)->wherePivot('role', 'leader')->exists())->toBeTrue();
});
