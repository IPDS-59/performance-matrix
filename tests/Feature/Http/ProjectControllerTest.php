<?php

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
