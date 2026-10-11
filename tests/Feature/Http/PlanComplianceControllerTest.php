<?php

use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;

function complianceTeam(string $name = 'Tim MTI', string $rkId = '55'): array
{
    $pjUser = staffUser();
    $pj = Employee::factory()->create(['user_id' => $pjUser->id, 'is_active' => true, 'display_name' => 'Sukma']);
    $team = Team::factory()->create(['leader_id' => $pj->id, 'name' => $name]);
    $pj->teams()->attach($team->id, ['role' => 'leader', 'is_primary' => true]);
    $ani = Employee::factory()->create(['user_id' => User::factory()->create()->id, 'is_active' => true, 'display_name' => 'Ani']);
    $ani->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'kip_external_id' => $rkId]);

    return [$pjUser, $pj, $ani, $team, $rk];
}

beforeEach(fn () => Carbon::setTestNow('2026-10-14 10:00:00')); // Wednesday of the 3rd week of Q4
afterEach(fn () => Carbon::setTestNow());

it('shows the planning rate per team and week, and the outcome of finished weeks', function () {
    [$pjUser, $pj, $ani, $team, $rk] = complianceTeam();
    // Week of 5 Oct: Ani planned and finished it in kipApp. Week of 12 Oct: nobody planned.
    PlanItem::create(['team_id' => $team->id, 'employee_id' => $ani->id, 'performance_plan_id' => $rk->id, 'description' => 'x', 'date_start' => '2026-10-05', 'date_end' => '2026-10-07']);
    KipActivity::factory()->create(['employee_id' => $ani->id, 'rk_external_id' => '55', 'activity_date_start' => '2026-10-06', 'activity_date_end' => '2026-10-06', 'progress' => 100]);

    $this->actingAs($pjUser)->get(route('plan-compliance.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Kinetik/PlanCompliance')
        ->where('weeks', ['2026-09-28', '2026-10-05', '2026-10-12'])
        ->where('teams.0.name', 'Tim MTI')
        ->where('teams.0.weeks.1.planners', 1)->where('teams.0.weeks.1.members', 2)
        ->where('teams.0.weeks.1.done', 1)->where('teams.0.weeks.1.not_started', 0)
        ->where('teams.0.weeks.2.planners', 0)->where('teams.0.weeks.2.done', null)
        ->where('teams.0.missing_this_week', fn ($names) => collect($names)->sort()->values()->all() === ['Ani', 'Sukma']));
});

it('lets the head and admins see every team but a PJ only their own', function () {
    [$pjUser] = complianceTeam('Tim A');
    complianceTeam('Tim B', '66');

    $this->actingAs($pjUser)->get(route('plan-compliance.index'))->assertInertia(fn ($page) => $page->has('teams', 1));
    $this->actingAs(adminUser())->get(route('plan-compliance.index'))->assertInertia(fn ($page) => $page->where('teams', fn ($teams) => collect($teams)->pluck('name')->contains('Tim B')));
    $this->actingAs(headUser())->get(route('plan-compliance.index'))->assertInertia(fn ($page) => $page->where('teams', fn ($teams) => collect($teams)->pluck('name')->contains('Tim A')));
});

it('forbids regular members and guests', function () {
    [, , $ani] = complianceTeam();

    $this->actingAs($ani->user)->get(route('plan-compliance.index'))->assertForbidden();
    auth()->logout();
    $this->get(route('plan-compliance.index'))->assertRedirect(route('login'));
});
