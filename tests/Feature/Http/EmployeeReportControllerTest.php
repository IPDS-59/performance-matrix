<?php

use App\Models\Employee;
use App\Models\Project;

it('redirects guests to login', function () {
    $this->get(route('laporan.pegawai'))->assertRedirect(route('login'));
});

it('forbids access for staff', function () {
    $this->actingAs(staffUser())
        ->get(route('laporan.pegawai'))
        ->assertForbidden();
});

it('renders report page for admin', function () {
    $this->actingAs(adminUser())
        ->get(route('laporan.pegawai'))
        ->assertInertia(fn ($page) => $page
            ->component('Laporan/Pegawai')
            ->has('top10')
            ->has('top10ByProjects')
            ->has('employees')
            ->has('filters')
        );
});

it('renders report page for head', function () {
    $this->actingAs(headUser())
        ->get(route('laporan.pegawai'))
        ->assertInertia(fn ($page) => $page
            ->component('Laporan/Pegawai')
            ->has('top10')
            ->has('employees')
            ->has('filters')
        );
});

it('accepts year and month filter params', function () {
    $this->actingAs(adminUser())
        ->get(route('laporan.pegawai', ['year' => 2025, 'month' => 6]))
        ->assertInertia(fn ($page) => $page
            ->where('filters.year', 2025)
            ->where('filters.month', 6)
        );
});

it('includes project counts for each active employee', function () {
    $employee = Employee::factory()->create(['is_active' => true]);
    $project = Project::factory()->create();
    $project->members()->attach($employee->id, ['role' => 'leader']);

    $this->actingAs(adminUser())
        ->get(route('laporan.pegawai'))
        ->assertInertia(fn ($page) => $page
            ->where('employees.0.id', $employee->id)
        );
});
