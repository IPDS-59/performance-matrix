<?php

use App\Models\ActivityClaim;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\WorkItem;

it('belongs to an employee', function () {
    $claim = ActivityClaim::factory()->create();

    expect($claim->employee)->toBeInstanceOf(Employee::class);
});

it('belongs to a performance plan', function () {
    $claim = ActivityClaim::factory()->create();

    expect($claim->performancePlan)->toBeInstanceOf(PerformancePlan::class);
});

it('belongs to a kip activity optionally', function () {
    $employee = Employee::factory()->create();
    $kipActivity = KipActivity::factory()->create(['employee_id' => $employee->id]);

    $claim = ActivityClaim::factory()->create([
        'employee_id' => $employee->id,
        'kip_activity_id' => $kipActivity->id,
    ]);
    $standalone = ActivityClaim::factory()->create(['kip_activity_id' => null]);

    expect($claim->kipActivity)->toBeInstanceOf(KipActivity::class);
    expect($standalone->kipActivity)->toBeNull();
});

it('belongs to a work item optionally', function () {
    $plan = PerformancePlan::factory()->create();
    $workItem = WorkItem::factory()->create(['performance_plan_id' => $plan->id]);

    $withItem = ActivityClaim::factory()->create([
        'performance_plan_id' => $plan->id,
        'work_item_id' => $workItem->id,
    ]);
    $without = ActivityClaim::factory()->create(['work_item_id' => null]);

    expect($withItem->workItem)->toBeInstanceOf(WorkItem::class);
    expect($without->workItem)->toBeNull();
});

it('kip activity has inverse claim relation', function () {
    $employee = Employee::factory()->create();
    $kipActivity = KipActivity::factory()->create(['employee_id' => $employee->id]);
    ActivityClaim::factory()->create([
        'employee_id' => $employee->id,
        'kip_activity_id' => $kipActivity->id,
    ]);

    expect($kipActivity->claim)->toBeInstanceOf(ActivityClaim::class);
});

it('casts dates and decimals correctly', function () {
    $claim = ActivityClaim::factory()->create([
        'target' => 10.0,
        'realization' => 7.5,
        'achievement' => 75.0,
        'period_year' => 2026,
        'period_quarter' => 2,
        'period_month' => 4,
    ]);

    expect((float) $claim->target)->toBe(10.0);
    expect((float) $claim->realization)->toBe(7.5);
    expect((float) $claim->achievement)->toBe(75.0);
    expect($claim->period_year)->toBe(2026);
    expect($claim->period_quarter)->toBe(2);
    expect($claim->period_month)->toBe(4);
});
