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
