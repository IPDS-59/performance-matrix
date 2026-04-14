<?php

use App\Models\Employee;
use App\Models\EmployeeTeamHistory;
use App\Models\Team;

it('redirects guests to login', function () {
    $this->get(route('employees.index'))->assertRedirect(route('login'));
});

it('renders index for admin', function () {
    $this->actingAs(adminUser())
        ->get(route('employees.index'))
        ->assertInertia(fn ($page) => $page->component('Employees/Index')->has('employees'));
});

it('denies index for staff', function () {
    $this->actingAs(staffUser())
        ->get(route('employees.index'))
        ->assertForbidden();
});

it('renders create form with teams', function () {
    $this->actingAs(adminUser())
        ->get(route('employees.create'))
        ->assertInertia(fn ($page) => $page->component('Employees/Create')->has('teams'));
});

it('stores an employee and redirects', function () {
    $team = Team::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('employees.store'), [
            'name' => 'Budi Santoso',
            'team_id' => $team->id,
            'is_active' => true,
        ])
        ->assertRedirect(route('employees.index'));

    expect(Employee::where('name', 'Budi Santoso')->exists())->toBeTrue();
});

it('validates required name on store', function () {
    $this->actingAs(adminUser())
        ->post(route('employees.store'), [])
        ->assertSessionHasErrors(['name']);
});

it('renders edit form for admin', function () {
    $employee = Employee::factory()->create();

    $this->actingAs(adminUser())
        ->get(route('employees.edit', $employee))
        ->assertInertia(fn ($page) => $page->component('Employees/Edit')->has('employee'));
});

it('updates an employee and redirects', function () {
    $employee = Employee::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('employees.update', $employee), [
            'name' => 'Updated Name',
            'is_active' => true,
        ])
        ->assertRedirect(route('employees.index'));

    expect($employee->fresh()->name)->toBe('Updated Name');
});

it('deletes an employee and redirects', function () {
    $employee = Employee::factory()->create();

    $this->actingAs(adminUser())
        ->delete(route('employees.destroy', $employee))
        ->assertRedirect(route('employees.index'));

    expect(Employee::find($employee->id))->toBeNull();
});

it('stores a mutation for an employee', function () {
    $employee = Employee::factory()->create();
    $newTeam = Team::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('employees.mutasi', $employee), [
            'team_id' => $newTeam->id,
            'started_at' => '2026-04-01',
            'notes' => 'Mutasi ke tim baru',
        ])
        ->assertRedirect();

    expect(EmployeeTeamHistory::where('employee_id', $employee->id)->exists())->toBeTrue();
    expect($employee->fresh()->team_id)->toBe($newTeam->id);
});

it('validates required fields on mutation store', function () {
    $employee = Employee::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('employees.mutasi', $employee), [])
        ->assertSessionHasErrors(['team_id', 'started_at']);
});

it('closes previous open history when storing a new mutation', function () {
    $employee = Employee::factory()->create();
    $oldTeam = Team::factory()->create();
    $newTeam = Team::factory()->create();

    EmployeeTeamHistory::factory()->create([
        'employee_id' => $employee->id,
        'team_id' => $oldTeam->id,
        'started_at' => '2025-01-01',
        'ended_at' => null,
    ]);

    $this->actingAs(adminUser())
        ->post(route('employees.mutasi', $employee), [
            'team_id' => $newTeam->id,
            'started_at' => '2026-04-01',
        ]);

    $closed = EmployeeTeamHistory::where('employee_id', $employee->id)
        ->where('team_id', $oldTeam->id)
        ->first();

    expect($closed->ended_at)->not->toBeNull();
});

it('returns mutation history as json', function () {
    $employee = Employee::factory()->create();
    EmployeeTeamHistory::factory()->create(['employee_id' => $employee->id]);

    $this->actingAs(adminUser())
        ->getJson(route('employees.mutasi.history', $employee))
        ->assertOk()
        ->assertJsonCount(1);
});
