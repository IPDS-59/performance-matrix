<?php

use App\Models\ActivityClaim;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\RecapOverride;
use App\Models\Team;
use App\Services\Kinetik\RecapAggregator;
use Carbon\Carbon;

beforeEach(function () {
    $this->aggregator = app(RecapAggregator::class);
});

function recapClaim(PerformancePlan $plan, array $attrs = []): ActivityClaim
{
    return ActivityClaim::factory()->saved()->create(array_merge([
        'employee_id' => Employee::factory(),
        'performance_plan_id' => $plan->id,
    ], $attrs));
}

// ── Project-scoped RK (existing path) ────────────────────────────────────────

it('segments weekly claims by project and aggregates RK rows', function () {
    $team = Team::factory()->create();
    $project = Project::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id]);

    $weekStart = Carbon::parse('2026-06-01'); // a Monday
    $common = ['week_start' => $weekStart->toDateString(), 'period_year' => 2026, 'period_month' => 6, 'period_quarter' => 2];

    recapClaim($plan, array_merge($common, ['target' => 10, 'realization' => 6]));
    recapClaim($plan, array_merge($common, ['target' => 10, 'realization' => 8]));

    $segments = $this->aggregator->weekly($team, $weekStart->toDateString());

    expect($segments)->toHaveCount(1);
    expect($segments[0]['project_id'])->toBe($project->id);
    expect($segments[0]['rows'])->toHaveCount(1);

    $row = $segments[0]['rows'][0];
    expect($row['target'])->toBe(20.0);
    expect($row['realization'])->toBe(14.0);
    expect($row['achievement'])->toBe(70.0);
    expect($row['contributors'])->toHaveCount(2);
});

it('excludes claims from other teams', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();

    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);
    $otherPlan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $otherTeam->id])->id,
    ]);

    $weekStart = Carbon::parse('2026-06-01');
    $common = ['week_start' => $weekStart->toDateString(), 'period_year' => 2026, 'period_month' => 6, 'period_quarter' => 2];

    recapClaim($plan, $common);
    recapClaim($otherPlan, $common);

    $segments = $this->aggregator->weekly($team, $weekStart->toDateString());

    expect($segments)->toHaveCount(1);
    expect($segments[0]['project_id'])->not->toBe($otherPlan->project_id);
});

it('ignores draft (unsaved) claims', function () {
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $weekStart = Carbon::parse('2026-06-01');
    ActivityClaim::factory()->create([
        'performance_plan_id' => $plan->id,
        'status' => 'draft',
        'week_start' => $weekStart->toDateString(),
    ]);

    expect($this->aggregator->weekly($team, $weekStart->toDateString()))->toBeEmpty();
});

it('merges paraphrase override into the monthly recap', function () {
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    recapClaim($plan, [
        'period_year' => 2026, 'period_month' => 6, 'period_quarter' => 2,
        'obstacle' => 'kendala asli',
    ]);

    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'month',
        'period_year' => 2026,
        'period_month' => 6,
        'obstacle' => 'kendala diparafrase',
    ]);

    $row = $this->aggregator->monthly($team, 2026, 6)[0]['rows'][0];

    expect($row['obstacle'])->toBe('kendala diparafrase');
    expect($row['obstacle_aggregated'])->toBe('kendala asli');
    expect($row['is_overridden'])->toBeTrue();
});

it('exposes FRA follow-up fields in the quarterly recap', function () {
    $team = Team::factory()->create();
    $pic = Employee::factory()->create(['display_name' => 'Budi']);
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    recapClaim($plan, ['period_year' => 2026, 'period_quarter' => 2, 'period_month' => 6]);

    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'quarter',
        'period_year' => 2026,
        'period_month' => null,
        'period_quarter' => 2,
        'follow_up_evidence_url' => 'https://example.test/bukti',
        'follow_up_pic_employee_id' => $pic->id,
        'follow_up_deadline' => '2026-09-30',
    ]);

    $row = $this->aggregator->quarterly($team, 2026, 2)[0]['rows'][0];

    expect($row['follow_up_evidence_url'])->toBe('https://example.test/bukti');
    expect($row['follow_up_pic'])->toBe('Budi');
    expect($row['follow_up_deadline'])->toBe('2026-09-30');
});

// ── Team-scoped RK (kipApp RKs with project_id = null) ───────────────────────

it('includes weekly claims on team-scoped RKs (project_id = null)', function () {
    $team = Team::factory()->create(['name' => 'Tim Alpha']);
    $member = Employee::factory()->create(['display_name' => 'Siti']);
    $plan = PerformancePlan::factory()->create([
        'project_id' => null,
        'team_id' => $team->id,
        'description' => 'RK Tim Alpha',
    ]);

    $weekStart = Carbon::parse('2026-06-01');
    recapClaim($plan, [
        'employee_id' => $member->id,
        'week_start' => $weekStart->toDateString(),
        'period_year' => 2026,
        'period_month' => 6,
        'period_quarter' => 2,
        'target' => 5,
        'realization' => 3,
    ]);

    $segments = $this->aggregator->weekly($team, $weekStart->toDateString());

    expect($segments)->toHaveCount(1);
    expect($segments[0]['project_name'])->toBe('Tim Alpha');
    expect($segments[0]['rows'])->toHaveCount(1);
    expect($segments[0]['rows'][0]['rk_description'])->toBe('RK Tim Alpha');
    expect($segments[0]['rows'][0]['contributors'])->toContain('Siti');
});

it('includes monthly claims on team-scoped RKs', function () {
    $team = Team::factory()->create(['name' => 'Tim Beta']);
    $plan = PerformancePlan::factory()->create([
        'project_id' => null,
        'team_id' => $team->id,
    ]);

    recapClaim($plan, [
        'period_year' => 2026,
        'period_month' => 5,
        'period_quarter' => 2,
    ]);

    $segments = $this->aggregator->monthly($team, 2026, 5);

    expect($segments)->toHaveCount(1);
    expect($segments[0]['rows'])->toHaveCount(1);
});

it('includes quarterly claims on team-scoped RKs', function () {
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create([
        'project_id' => null,
        'team_id' => $team->id,
    ]);

    recapClaim($plan, [
        'period_year' => 2026,
        'period_month' => 4,
        'period_quarter' => 2,
    ]);

    $segments = $this->aggregator->quarterly($team, 2026, 2);

    expect($segments)->toHaveCount(1);
    expect($segments[0]['rows'])->toHaveCount(1);
});

it('does not include team-scoped RK claims from another team', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();

    $otherPlan = PerformancePlan::factory()->create([
        'project_id' => null,
        'team_id' => $otherTeam->id,
    ]);

    $weekStart = Carbon::parse('2026-06-01');
    recapClaim($otherPlan, [
        'week_start' => $weekStart->toDateString(),
        'period_year' => 2026,
        'period_month' => 6,
        'period_quarter' => 2,
    ]);

    expect($this->aggregator->weekly($team, $weekStart->toDateString()))->toBeEmpty();
});

it('groups team-scoped and project-scoped RKs in separate segments', function () {
    $team = Team::factory()->create(['name' => 'Tim Gamma']);
    $project = Project::factory()->create(['team_id' => $team->id]);

    $teamPlan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    $projectPlan = PerformancePlan::factory()->create(['project_id' => $project->id, 'team_id' => null]);

    $weekStart = Carbon::parse('2026-06-01');
    $common = ['week_start' => $weekStart->toDateString(), 'period_year' => 2026, 'period_month' => 6, 'period_quarter' => 2];

    recapClaim($teamPlan, $common);
    recapClaim($projectPlan, $common);

    $segments = $this->aggregator->weekly($team, $weekStart->toDateString());

    expect($segments)->toHaveCount(2);
});

// ── latestWeekStart / latestClaimPeriod helpers ───────────────────────────────

it('latestWeekStart returns the most recent week_start for the team', function () {
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create([
        'project_id' => null,
        'team_id' => $team->id,
    ]);

    recapClaim($plan, ['week_start' => '2026-05-04', 'period_year' => 2026, 'period_month' => 5, 'period_quarter' => 2]);
    recapClaim($plan, ['week_start' => '2026-06-01', 'period_year' => 2026, 'period_month' => 6, 'period_quarter' => 2]);

    $latest = $this->aggregator->latestWeekStart($team);

    expect(Carbon::parse($latest)->toDateString())->toBe('2026-06-01');
});

it('latestWeekStart returns null when no claims exist', function () {
    $team = Team::factory()->create();

    expect($this->aggregator->latestWeekStart($team))->toBeNull();
});

it('latestClaimPeriod returns the claim with the highest period year/month', function () {
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);

    recapClaim($plan, ['period_year' => 2026, 'period_month' => 3, 'period_quarter' => 1]);
    $latest = recapClaim($plan, ['period_year' => 2026, 'period_month' => 6, 'period_quarter' => 2]);

    $result = $this->aggregator->latestClaimPeriod($team);

    expect($result)->not->toBeNull();
    expect($result->period_year)->toBe(2026);
    expect($result->period_month)->toBe(6);
});

// ── Weekly override — paraphrase + confirmation ───────────────────────────────

it('weekly override merges pj_obstacle and sets is_overridden', function () {
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $weekStart = '2026-06-01';

    recapClaim($plan, [
        'week_start' => $weekStart,
        'period_year' => 2026,
        'period_month' => 6,
        'period_quarter' => 2,
        'obstacle' => 'kendala asli',
    ]);

    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'period_month' => null,
        'week_start' => $weekStart,
        'obstacle' => 'parafrase PJ',
        'solution' => null,
        'follow_up_plan' => null,
        'confirmed_at' => null,
        'confirmed_by' => null,
    ]);

    $row = $this->aggregator->weekly($team, $weekStart)[0]['rows'][0];

    expect($row['pj_obstacle'])->toBe('parafrase PJ');
    expect($row['obstacle_aggregated'])->toBe('kendala asli');
    expect($row['is_overridden'])->toBeTrue();
    expect($row['is_confirmed'])->toBeFalse();
    expect($row['confirmed_by'])->toBeNull();
});

it('confirmed weekly override surfaces is_confirmed true and confirmed_by name', function () {
    $team = Team::factory()->create();
    $pj = Employee::factory()->create(['display_name' => 'Ahmad PJ']);
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $weekStart = '2026-06-01';

    recapClaim($plan, [
        'week_start' => $weekStart,
        'period_year' => 2026,
        'period_month' => 6,
        'period_quarter' => 2,
    ]);

    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'period_month' => null,
        'week_start' => $weekStart,
        'confirmed_at' => now(),
        'confirmed_by' => $pj->id,
    ]);

    $row = $this->aggregator->weekly($team, $weekStart)[0]['rows'][0];

    expect($row['is_confirmed'])->toBeTrue();
    expect($row['confirmed_by'])->toBe('Ahmad PJ');
});

it('row with no weekly override has is_confirmed false and pj fields null', function () {
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $weekStart = '2026-06-01';

    recapClaim($plan, [
        'week_start' => $weekStart,
        'period_year' => 2026,
        'period_month' => 6,
        'period_quarter' => 2,
    ]);

    $row = $this->aggregator->weekly($team, $weekStart)[0]['rows'][0];

    expect($row['is_overridden'])->toBeFalse();
    expect($row['is_confirmed'])->toBeFalse();
    expect($row['pj_obstacle'])->toBeNull();
});

// ── Inherited (rolled-up) weekly paraphrase ───────────────────────────────────

it('weekly override in January surfaces as inherited_* on the January monthly row when no monthly override exists', function () {
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    // A claim in January 2026
    recapClaim($plan, [
        'period_year' => 2026,
        'period_month' => 1,
        'period_quarter' => 1,
        'week_start' => '2026-01-05',
    ]);

    // Weekly override for that week
    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'period_month' => null,
        'period_quarter' => null,
        'week_start' => '2026-01-05',
        'obstacle' => 'kendala minggu pertama',
        'solution' => 'solusi mingguan',
        'follow_up_plan' => null,
        'confirmed_at' => null,
        'confirmed_by' => null,
    ]);

    $row = $this->aggregator->monthly($team, 2026, 1)[0]['rows'][0];

    expect($row['inherited_obstacle'])->toBe('kendala minggu pertama');
    expect($row['inherited_solution'])->toBe('solusi mingguan');
    expect($row['pj_obstacle'])->toBeNull();
    expect($row['is_overridden'])->toBeFalse();
});

it('weekly override in January surfaces as inherited_* on the Q1 quarterly row', function () {
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    recapClaim($plan, [
        'period_year' => 2026,
        'period_month' => 1,
        'period_quarter' => 1,
        'week_start' => '2026-01-05',
    ]);

    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'period_month' => null,
        'period_quarter' => null,
        'week_start' => '2026-01-05',
        'obstacle' => 'kendala Q1',
        'solution' => 'solusi Q1',
        'follow_up_plan' => null,
        'confirmed_at' => null,
        'confirmed_by' => null,
    ]);

    $row = $this->aggregator->quarterly($team, 2026, 1)[0]['rows'][0];

    expect($row['inherited_obstacle'])->toBe('kendala Q1');
    expect($row['inherited_solution'])->toBe('solusi Q1');
    expect($row['pj_obstacle'])->toBeNull();
});

it('monthly override sets pj_obstacle while inherited_* still reflects the weekly roll-up', function () {
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    recapClaim($plan, [
        'period_year' => 2026,
        'period_month' => 1,
        'period_quarter' => 1,
        'week_start' => '2026-01-05',
    ]);

    // Weekly override
    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'period_month' => null,
        'period_quarter' => null,
        'week_start' => '2026-01-05',
        'obstacle' => 'kendala dari mingguan',
        'solution' => null,
        'follow_up_plan' => null,
        'confirmed_at' => null,
        'confirmed_by' => null,
    ]);

    // Monthly override
    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'month',
        'period_year' => 2026,
        'period_month' => 1,
        'period_quarter' => null,
        'week_start' => null,
        'obstacle' => 'kendala bulanan yang sudah diedit',
        'solution' => null,
        'follow_up_plan' => null,
        'confirmed_at' => null,
        'confirmed_by' => null,
    ]);

    $row = $this->aggregator->monthly($team, 2026, 1)[0]['rows'][0];

    expect($row['pj_obstacle'])->toBe('kendala bulanan yang sudah diedit');
    expect($row['inherited_obstacle'])->toBe('kendala dari mingguan');
    expect($row['is_overridden'])->toBeTrue();
});

it('weekly override in a different month does not leak into this month inherited', function () {
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    // Claim in January
    recapClaim($plan, [
        'period_year' => 2026,
        'period_month' => 1,
        'period_quarter' => 1,
        'week_start' => '2026-01-05',
    ]);

    // Weekly override in February (different month)
    RecapOverride::factory()->create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'period_month' => null,
        'period_quarter' => null,
        'week_start' => '2026-02-02',
        'obstacle' => 'kendala bulan Februari',
        'solution' => null,
        'follow_up_plan' => null,
        'confirmed_at' => null,
        'confirmed_by' => null,
    ]);

    // January monthly should have no inherited from February's override
    $row = $this->aggregator->monthly($team, 2026, 1)[0]['rows'][0];

    expect($row['inherited_obstacle'])->toBeNull();
    expect($row['inherited_solution'])->toBeNull();
});
