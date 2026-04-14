<?php

use App\Models\Employee;

it('redirects guests to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('renders staff dashboard for staff user', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('role', 'staff')
            ->has('employee')
        );
});

it('renders head dashboard for head user', function () {
    $this->actingAs(headUser())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('role', 'head')
            ->has('teams')
        );
});

it('renders admin dashboard for admin user', function () {
    $this->actingAs(adminUser())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('role', 'admin')
            ->has('teams')
            ->has('org_avg')
            ->has('trend')
        );
});

it('renders staff dashboard with no employee when not linked', function () {
    $user = staffUser();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('role', 'staff')
            ->missing('employee')
            ->has('filters')
        );
});

it('renders matrix for admin', function () {
    $this->actingAs(adminUser())
        ->get(route('matrix'))
        ->assertInertia(fn ($page) => $page
            ->component('Matrix/Index')
            ->has('employees')
            ->has('projects')
            ->has('teams')
        );
});

it('renders matrix for head', function () {
    $this->actingAs(headUser())
        ->get(route('matrix'))
        ->assertInertia(fn ($page) => $page->component('Matrix/Index'));
});

it('renders matrix for staff', function () {
    $this->actingAs(staffUser())
        ->get(route('matrix'))
        ->assertInertia(fn ($page) => $page->component('Matrix/Index'));
});
