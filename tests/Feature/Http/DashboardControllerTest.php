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
        ->get(route('monitoring'))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('role', 'staff')
            ->has('employee')
        );
});

it('renders the monitoring dashboard for head user', function () {
    $this->actingAs(headUser())
        ->get(route('monitoring'))
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('role', 'head')
            ->has('teams')
        );
});

it('shows the head the four questions on the beranda', function () {
    $this->travelTo('2026-10-28 10:00:00');
    $this->actingAs(headUser());
    $team = Team::factory()->create();
    [$planned, $sentAll, $silent] = Employee::factory()->count(3)->create(['is_active' => true]);
    foreach ([$planned, $sentAll, $silent] as $e) {
        $e->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    }
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    $item = fn (Employee $e, $sentAt) => PlanItem::create([
        'team_id' => $team->id, 'employee_id' => $e->id, 'performance_plan_id' => $plan->id, 'description' => 'x',
        'date_start' => '2026-10-27', 'date_end' => '2026-10-27', 'kip_sent_at' => $sentAt,
    ]);
    $item($planned, null);
    $item($sentAll, now());

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->component('HeadHome')
        ->where('plans.filled', 2)
        ->where('plans.total', 3)
        ->has('plans.missing', 1)
        ->where('sent.sent', 1)
        ->has('sent.pending', 1)
        ->where('meetings.total', Team::count())
        ->has('meetings.missing', Team::count())
        ->where('skp.count', 0));
});

it('ranks employees by how many of the last 8 weeks they planned', function () {
    $this->travelTo('2026-10-28 10:00:00');
    $team = Team::factory()->create();
    $a = Employee::factory()->create(['is_active' => true]);
    $b = Employee::factory()->create(['is_active' => true]);
    $a->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $b->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    foreach (['2026-10-27', '2026-10-20', '2026-10-20'] as $date) {
        PlanItem::create(['team_id' => $team->id, 'employee_id' => $a->id, 'performance_plan_id' => $plan->id, 'description' => 'x', 'date_start' => $date, 'date_end' => $date]);
    }

    $this->actingAs(headUser())->get(route('monitoring'))->assertInertia(fn ($page) => $page
        ->where('top_employees_by_consistency.0.id', $a->id)
        ->where('top_employees_by_consistency.0.weeks_planned', 2)
        ->where('top_employees_by_consistency.1.weeks_planned', 0));
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
        ->get(route('monitoring'))
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

    $this->actingAs($user)->get(route('monitoring', ['year' => 2026, 'month' => 10]))
        ->assertInertia(fn ($page) => $page
            ->where('personal_stats.avg_achievement', 75)
            ->where('personal_stats.has_achievement', true)
            ->where('projects.0.name', 'RK Dasbor')
            ->where('projects.0.achievement', 75)
            ->where('team_progress.'.$team->id.'.avg_achievement', 75)
            ->where('top_employees_by_achievement.0.avg_achievement', 75));
});

it('shows a member what to do this week on the beranda', function () {
    $this->travelTo('2026-10-28 10:00:00');
    $user = staffUser();
    $me = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    $item = fn (string $date, array $extra = []) => PlanItem::create([
        'team_id' => $team->id, 'employee_id' => $me->id, 'performance_plan_id' => $plan->id, 'description' => 'k', 'date_start' => $date, 'date_end' => $date, ...$extra,
    ]);
    $item('2026-10-27', ['kip_sent_at' => now()]);
    $item('2026-10-20', ['capaian' => 3]);
    $item('2026-10-21');

    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->component('MemberHome')
        ->where('plan.count', 1)
        ->where('last_week.total', 2)
        ->where('last_week.filled', 1)
        ->has('last_week.missing', 1)
        ->where('sent.sent', 1)
        ->where('sent.total', 3)
        ->has('sent.pending', 2));
});
