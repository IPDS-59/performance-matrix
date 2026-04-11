<?php

use App\Models\Team;

it('redirects guests to login', function () {
    $this->get(route('teams.index'))->assertRedirect(route('login'));
});

it('renders index for admin', function () {
    $this->actingAs(adminUser())
        ->get(route('teams.index'))
        ->assertInertia(fn ($page) => $page->component('Teams/Index')->has('teams'));
});

it('denies index for staff', function () {
    $this->actingAs(staffUser())
        ->get(route('teams.index'))
        ->assertForbidden();
});

it('renders create form for admin', function () {
    $this->actingAs(adminUser())
        ->get(route('teams.create'))
        ->assertInertia(fn ($page) => $page->component('Teams/Create'));
});

it('stores a team and redirects', function () {
    $this->actingAs(adminUser())
        ->post(route('teams.store'), [
            'name' => 'Tim Neraca',
            'code' => 'NRC',
            'description' => null,
            'is_active' => true,
        ])
        ->assertRedirect(route('teams.index'));

    expect(Team::where('code', 'NRC')->exists())->toBeTrue();
});

it('validates required fields on store', function () {
    $this->actingAs(adminUser())
        ->post(route('teams.store'), [])
        ->assertSessionHasErrors(['name', 'code']);
});

it('renders edit form for admin', function () {
    $team = Team::factory()->create();

    $this->actingAs(adminUser())
        ->get(route('teams.edit', $team))
        ->assertInertia(fn ($page) => $page->component('Teams/Edit')->has('team'));
});

it('updates a team and redirects', function () {
    $team = Team::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('teams.update', $team), [
            'name' => 'Updated Name',
            'code' => $team->code,
            'is_active' => true,
        ])
        ->assertRedirect(route('teams.index'));

    expect($team->fresh()->name)->toBe('Updated Name');
});

it('deletes a team and redirects', function () {
    $team = Team::factory()->create();

    $this->actingAs(adminUser())
        ->delete(route('teams.destroy', $team))
        ->assertRedirect(route('teams.index'));

    expect(Team::find($team->id))->toBeNull();
});
