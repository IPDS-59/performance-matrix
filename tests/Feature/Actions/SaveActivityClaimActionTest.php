<?php

use App\Actions\Kinetik\SaveActivityClaimAction;
use App\Models\ActivityClaim;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Auth\Access\AuthorizationException;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Create an employee that is a member of a team that owns a project with a plan.
 */
function memberSetup(string $role = 'member'): array
{
    $team = Team::factory()->create();
    $project = Project::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id]);
    $employee = Employee::factory()->create();

    $employee->teams()->attach($team->id, [
        'role' => $role,
        'is_primary' => true,
        'started_at' => now()->subYear()->toDateString(),
        'ended_at' => null,
    ]);

    return compact('team', 'project', 'plan', 'employee');
}

function baseData(int $planId, string $dateStart = '2026-04-14'): array
{
    return [
        'performance_plan_id' => $planId,
        'activity_date_start' => $dateStart,
        'target' => 10.0,
        'realization' => 8.0,
        'target_unit' => 'Kegiatan',
        'status' => 'draft',
    ];
}

// ── Achievement computation ───────────────────────────────────────────────────

it('computes achievement correctly', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();
    $action = app(SaveActivityClaimAction::class);

    $claim = $action->execute($employee, baseData($plan->id));

    expect((float) $claim->achievement)->toBe(80.0);
});

it('computes achievement as null when target is zero', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();
    $action = app(SaveActivityClaimAction::class);

    $claim = $action->execute($employee, array_merge(baseData($plan->id), [
        'target' => 0,
        'realization' => 5.0,
    ]));

    expect($claim->achievement)->toBeNull();
});

it('computes achievement as null when target is null', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();
    $action = app(SaveActivityClaimAction::class);

    $claim = $action->execute($employee, array_merge(baseData($plan->id), [
        'target' => null,
        'realization' => 5.0,
    ]));

    expect($claim->achievement)->toBeNull();
});

// ── Period derivation ─────────────────────────────────────────────────────────

it('derives week_start as Monday for a known date', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();
    $action = app(SaveActivityClaimAction::class);

    // 2026-04-14 is a Tuesday; Monday of that week is 2026-04-13
    $claim = $action->execute($employee, baseData($plan->id, '2026-04-14'));

    expect($claim->week_start->toDateString())->toBe('2026-04-13');
});

it('derives period_year, period_month, period_quarter correctly', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();
    $action = app(SaveActivityClaimAction::class);

    $claim = $action->execute($employee, baseData($plan->id, '2026-05-20'));

    expect($claim->period_year)->toBe(2026);
    expect($claim->period_month)->toBe(5);
    expect($claim->period_quarter)->toBe(2);
});

it('maps Q1 correctly (January)', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();

    $claim = app(SaveActivityClaimAction::class)->execute($employee, baseData($plan->id, '2026-01-15'));

    expect($claim->period_quarter)->toBe(1);
});

it('maps Q3 correctly (September)', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();

    $claim = app(SaveActivityClaimAction::class)->execute($employee, baseData($plan->id, '2026-09-01'));

    expect($claim->period_quarter)->toBe(3);
});

it('maps Q4 correctly (December)', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();

    $claim = app(SaveActivityClaimAction::class)->execute($employee, baseData($plan->id, '2026-12-31'));

    expect($claim->period_quarter)->toBe(4);
});

// ── KipActivity is_claimed flag ───────────────────────────────────────────────

it('marks kip_activity is_claimed true when status is saved', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();
    $kipActivity = KipActivity::factory()->create(['employee_id' => $employee->id, 'is_claimed' => false]);
    $action = app(SaveActivityClaimAction::class);

    $action->execute($employee, array_merge(baseData($plan->id), [
        'kip_activity_id' => $kipActivity->id,
        'status' => 'saved',
    ]));

    expect($kipActivity->fresh()->is_claimed)->toBeTrue();
});

it('marks kip_activity is_claimed false when status is draft', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();
    $kipActivity = KipActivity::factory()->create(['employee_id' => $employee->id, 'is_claimed' => false]);
    $action = app(SaveActivityClaimAction::class);

    $action->execute($employee, array_merge(baseData($plan->id), [
        'kip_activity_id' => $kipActivity->id,
        'status' => 'draft',
    ]));

    expect($kipActivity->fresh()->is_claimed)->toBeFalse();
});

// ── Idempotency ───────────────────────────────────────────────────────────────

it('is idempotent on kip_activity_id: second call updates same row', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();
    $kipActivity = KipActivity::factory()->create(['employee_id' => $employee->id]);
    $action = app(SaveActivityClaimAction::class);

    $first = $action->execute($employee, array_merge(baseData($plan->id), [
        'kip_activity_id' => $kipActivity->id,
    ]));

    $second = $action->execute($employee, array_merge(baseData($plan->id), [
        'kip_activity_id' => $kipActivity->id,
        'realization' => 9.0,
    ]));

    expect(ActivityClaim::count())->toBe(1);
    expect($first->id)->toBe($second->id);
    expect((float) $second->fresh()->realization)->toBe(9.0);
});

// ── Standalone (manual) claim ─────────────────────────────────────────────────

it('creates a standalone claim when no kip_activity_id', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();
    $action = app(SaveActivityClaimAction::class);

    $claim = $action->execute($employee, baseData($plan->id));

    expect($claim->kip_activity_id)->toBeNull();
    expect(ActivityClaim::count())->toBe(1);
});

// ── claimed_at stamp ──────────────────────────────────────────────────────────

it('sets claimed_at when status is saved', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();

    $claim = app(SaveActivityClaimAction::class)->execute($employee, array_merge(baseData($plan->id), [
        'status' => 'saved',
    ]));

    expect($claim->claimed_at)->not->toBeNull();
});

it('leaves claimed_at null when status is draft', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup();

    $claim = app(SaveActivityClaimAction::class)->execute($employee, baseData($plan->id));

    expect($claim->claimed_at)->toBeNull();
});

// ── Authorization ─────────────────────────────────────────────────────────────

it('allows claim into an RK of a team the employee is a member of', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup('member');

    $claim = app(SaveActivityClaimAction::class)->execute($employee, baseData($plan->id));

    expect($claim)->toBeInstanceOf(ActivityClaim::class);
});

it('allows claim into an RK of a team the employee leads', function () {
    ['plan' => $plan, 'employee' => $employee] = memberSetup('leader');

    $claim = app(SaveActivityClaimAction::class)->execute($employee, baseData($plan->id));

    expect($claim)->toBeInstanceOf(ActivityClaim::class);
});

it('rejects claim into an RK of a team the employee does not belong to', function () {
    $otherTeam = Team::factory()->create();
    $project = Project::factory()->create(['team_id' => $otherTeam->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id]);

    $employee = Employee::factory()->create();
    // Employee has NO team membership at all

    expect(fn () => app(SaveActivityClaimAction::class)->execute($employee, baseData($plan->id)))
        ->toThrow(AuthorizationException::class);
});

it('rejects claim when employee is in a different team than the RK', function () {
    $myTeam = Team::factory()->create();
    $employee = Employee::factory()->create();
    $employee->teams()->attach($myTeam->id, [
        'role' => 'member',
        'is_primary' => true,
        'started_at' => now()->subYear()->toDateString(),
        'ended_at' => null,
    ]);

    $otherTeam = Team::factory()->create();
    $project = Project::factory()->create(['team_id' => $otherTeam->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id]);

    expect(fn () => app(SaveActivityClaimAction::class)->execute($employee, baseData($plan->id)))
        ->toThrow(AuthorizationException::class);
});
