<?php

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;

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

it('hides personal cards for a head who is in no team', function () {
    $user = headUser();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->missing('personal_stats'));
});

it('computes achievement from the plan items of the month', function () {
    $user = staffUser();
    $me = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create(['leader_id' => $me->id]);
    $me->teams()->attach($team->id, ['role' => 'leader', 'is_primary' => true]);
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'description' => 'RK Dasbor']);
    $item = fn (array $extra) => PlanItem::create([
        'team_id' => $team->id, 'employee_id' => $me->id, 'performance_plan_id' => $plan->id,
        'description' => 'x', 'date_start' => '2026-10-05', 'date_end' => '2026-10-05', ...$extra,
    ]);
    $item(['target' => 10, 'capaian' => 5]);   // 50%
    $item(['target' => 4, 'capaian' => 4]);    // 100%
    $item(['target' => 4, 'capaian' => null]); // no capaian yet
    $item(['target' => 4, 'capaian' => 4, 'status' => 'cancelled']);
    $item(['target' => 4, 'capaian' => 4, 'date_start' => '2026-09-05', 'date_end' => '2026-09-05']); // another month

    $this->actingAs($user)->get(route('dashboard', ['year' => 2026, 'month' => 10]))
        ->assertInertia(fn ($page) => $page
            ->where('personal_stats.avg_achievement', 75)
            ->where('personal_stats.has_achievement', true)
            ->where('projects.0.name', 'RK Dasbor')
            ->where('projects.0.achievement', 75)
            ->where('team_progress.'.$team->id.'.avg_achievement', 75)
            ->where('top_employees_by_achievement.0.avg_achievement', 75));
});
