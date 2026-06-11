<?php

use App\Models\ActivityClaim;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;
use Carbon\Carbon;

// ── Index ─────────────────────────────────────────────────────────────────

it('redirects guests to login on weekly index', function () {
    $this->get(route('weekly.index'))->assertRedirect(route('login'));
});

it('renders weekly index for a user with an employee', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('weekly.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/WeeklyScrapper')
            ->has('activities')
            ->has('recap')
            ->has('plans')
            ->has('weekStart')
            ->has('weekEnd')
        );
});

it('renders weekly index with null employee when user has no employee record', function () {
    $user = staffUser();

    $this->actingAs($user)
        ->get(route('weekly.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/WeeklyScrapper')
            ->where('employee', null)
        );
});

it('scopes activities to the logged-in employee', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $otherEmployee = Employee::factory()->create();

    $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

    $myActivity = KipActivity::factory()->create([
        'employee_id' => $employee->id,
        'activity_date_start' => $weekStart,
    ]);

    $otherActivity = KipActivity::factory()->create([
        'employee_id' => $otherEmployee->id,
        'activity_date_start' => $weekStart,
    ]);

    $this->actingAs($user)
        ->get(route('weekly.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/WeeklyScrapper')
            ->where('activities.0.id', $myActivity->id)
            ->count('activities', 1)
        );
});

it('excludes activities outside the selected week', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $thisWeekStart = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();
    $lastWeekDate = Carbon::now()->subWeek()->toDateString();

    KipActivity::factory()->create([
        'employee_id' => $employee->id,
        'activity_date_start' => $thisWeekStart,
    ]);

    KipActivity::factory()->create([
        'employee_id' => $employee->id,
        'activity_date_start' => $lastWeekDate,
    ]);

    $this->actingAs($user)
        ->get(route('weekly.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/WeeklyScrapper')
            ->count('activities', 1)
        );
});

it('filters by the ?week query parameter', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $targetWeekStart = '2026-01-05'; // a known Monday

    KipActivity::factory()->create([
        'employee_id' => $employee->id,
        'activity_date_start' => $targetWeekStart,
    ]);

    $this->actingAs($user)
        ->get(route('weekly.index', ['week' => $targetWeekStart]))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/WeeklyScrapper')
            ->count('activities', 1)
        );
});

// ── Store Claim ───────────────────────────────────────────────────────────

it('saves an activity claim with computed achievement', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    $project = Project::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id]);

    $activity = KipActivity::factory()->create([
        'employee_id' => $employee->id,
        'activity_date_start' => '2026-06-02',
    ]);

    $this->actingAs($user)
        ->post(route('weekly.claim'), [
            'kip_activity_id' => $activity->id,
            'performance_plan_id' => $plan->id,
            'target' => '10',
            'realization' => '8',
            'obstacle' => 'Kendala teknis',
            'activity_date_start' => '2026-06-02',
            'status' => 'saved',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('activity_claims', [
        'employee_id' => $employee->id,
        'performance_plan_id' => $plan->id,
        'kip_activity_id' => $activity->id,
        'status' => 'saved',
    ]);

    $claim = ActivityClaim::where('kip_activity_id', $activity->id)->firstOrFail();
    expect((float) $claim->achievement)->toBe(80.0);

    // kip_activity should be marked claimed
    expect($activity->fresh()->is_claimed)->toBeTrue();
});

it('returns 403 when claiming an RK from a team the employee is not in', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $otherTeam = Team::factory()->create();
    $project = Project::factory()->create(['team_id' => $otherTeam->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id]);

    $activity = KipActivity::factory()->create([
        'employee_id' => $employee->id,
        'activity_date_start' => '2026-06-02',
    ]);

    $this->actingAs($user)
        ->post(route('weekly.claim'), [
            'kip_activity_id' => $activity->id,
            'performance_plan_id' => $plan->id,
            'obstacle' => 'Kendala teknis',
            'activity_date_start' => '2026-06-02',
            'status' => 'saved',
        ])
        ->assertRedirect();

    // Claim should not have been created
    $this->assertDatabaseMissing('activity_claims', [
        'kip_activity_id' => $activity->id,
        'performance_plan_id' => $plan->id,
    ]);
});

it('validates required performance_plan_id on claim store', function () {
    $user = staffUser();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('weekly.claim'), [
            'activity_date_start' => '2026-06-02',
        ])
        ->assertSessionHasErrors(['performance_plan_id']);
});

it('returns 403 when storing a claim without employee record', function () {
    $user = staffUser();
    $team = Team::factory()->create();
    $project = Project::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->post(route('weekly.claim'), [
            'performance_plan_id' => $plan->id,
            'activity_date_start' => '2026-06-02',
            'status' => 'saved',
        ])
        ->assertForbidden();
});

it('allows re-saving an existing claim (updateOrCreate)', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    $project = Project::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id]);

    $activity = KipActivity::factory()->create([
        'employee_id' => $employee->id,
        'activity_date_start' => '2026-06-02',
    ]);

    $payload = [
        'kip_activity_id' => $activity->id,
        'performance_plan_id' => $plan->id,
        'target' => '10',
        'realization' => '5',
        'obstacle' => 'Kendala teknis',
        'activity_date_start' => '2026-06-02',
        'status' => 'saved',
    ];

    $this->actingAs($user)->post(route('weekly.claim'), $payload);
    $this->actingAs($user)->post(route('weekly.claim'), array_merge($payload, ['realization' => '9']));

    expect(ActivityClaim::where('kip_activity_id', $activity->id)->count())->toBe(1);
    $claim = ActivityClaim::where('kip_activity_id', $activity->id)->first();
    expect((float) $claim->achievement)->toBe(90.0);
});

// ── Default week (latest activity) ───────────────────────────────────────

it('defaults Rekap Mingguan to the week of the latest activity', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    // 2026-01-05 is a Monday; no ?week param -> should anchor here.
    KipActivity::factory()->create([
        'employee_id' => $employee->id,
        'activity_date_start' => '2026-01-05',
    ]);

    $this->actingAs($user)
        ->get(route('weekly.index'))
        ->assertInertia(fn ($page) => $page
            ->where('weekStart', '2026-01-05')
            ->count('activities', 1)
        );
});

it('falls back to the current week when the employee has no activities', function () {
    $user = staffUser();
    Employee::factory()->create(['user_id' => $user->id]);

    $expected = Carbon::now()->startOfWeek(Carbon::MONDAY)->toDateString();

    $this->actingAs($user)
        ->get(route('weekly.index'))
        ->assertInertia(fn ($page) => $page->where('weekStart', $expected));
});

// ── Kendala required + PJ-only Solusi/RTL ────────────────────────────────

it('requires obstacle (kendala) on claim', function () {
    $user = staffUser();
    Employee::factory()->create(['user_id' => $user->id]);
    $plan = PerformancePlan::factory()->create();

    $this->actingAs($user)
        ->post(route('weekly.claim'), [
            'performance_plan_id' => $plan->id,
            'activity_date_start' => '2026-06-02',
        ])
        ->assertSessionHasErrors(['obstacle']);
});

it('strips solution and rtl for a non-PJ member', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $plan = PerformancePlan::factory()->create(['project_id' => Project::factory()->create(['team_id' => $team->id])->id]);
    $activity = KipActivity::factory()->create(['employee_id' => $employee->id, 'activity_date_start' => '2026-06-02']);

    $this->actingAs($user)->post(route('weekly.claim'), [
        'kip_activity_id' => $activity->id,
        'performance_plan_id' => $plan->id,
        'obstacle' => 'Kendala',
        'solution' => 'Solusi rahasia',
        'follow_up_plan' => 'RTL rahasia',
        'activity_date_start' => '2026-06-02',
        'status' => 'saved',
    ])->assertRedirect();

    $claim = ActivityClaim::where('kip_activity_id', $activity->id)->first();
    expect($claim->solution)->toBeNull()
        ->and($claim->follow_up_plan)->toBeNull();
});

it('keeps solution and rtl for a PJ', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create(['leader_id' => $employee->id]);
    $employee->teams()->attach($team->id, ['role' => 'leader', 'is_primary' => true]);
    $plan = PerformancePlan::factory()->create(['project_id' => Project::factory()->create(['team_id' => $team->id])->id]);
    $activity = KipActivity::factory()->create(['employee_id' => $employee->id, 'activity_date_start' => '2026-06-02']);

    $this->actingAs($user)
        ->get(route('weekly.index'))
        ->assertInertia(fn ($page) => $page->where('isPj', true));

    $this->actingAs($user)->post(route('weekly.claim'), [
        'kip_activity_id' => $activity->id,
        'performance_plan_id' => $plan->id,
        'obstacle' => 'Kendala',
        'solution' => 'Solusi PJ',
        'follow_up_plan' => 'RTL PJ',
        'activity_date_start' => '2026-06-02',
        'status' => 'saved',
    ])->assertRedirect();

    $claim = ActivityClaim::where('kip_activity_id', $activity->id)->first();
    expect($claim->solution)->toBe('Solusi PJ')
        ->and($claim->follow_up_plan)->toBe('RTL PJ');
});

it('allows claiming a team-scoped RK with no project (kipApp style)', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    // kipApp RK: team-scoped, project_id null.
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    $activity = KipActivity::factory()->create(['employee_id' => $employee->id, 'activity_date_start' => '2026-06-02']);

    $this->actingAs($user)->post(route('weekly.claim'), [
        'kip_activity_id' => $activity->id,
        'performance_plan_id' => $plan->id,
        'obstacle' => 'Kendala',
        'activity_date_start' => '2026-06-02',
        'status' => 'saved',
    ])->assertRedirect();

    $this->assertDatabaseHas('activity_claims', [
        'kip_activity_id' => $activity->id,
        'performance_plan_id' => $plan->id,
        'status' => 'saved',
    ]);
});
