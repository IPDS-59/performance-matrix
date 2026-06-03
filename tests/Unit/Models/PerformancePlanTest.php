<?php

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\WorkItem;

it('belongs to a project', function () {
    $plan = PerformancePlan::factory()->create();

    expect($plan->project)->toBeInstanceOf(Project::class);
});

it('has a pic employee optionally', function () {
    $employee = Employee::factory()->create();
    $plan = PerformancePlan::factory()->create(['pic_employee_id' => $employee->id]);
    $noPic = PerformancePlan::factory()->create(['pic_employee_id' => null]);

    expect($plan->pic)->toBeInstanceOf(Employee::class);
    expect($noPic->pic)->toBeNull();
});

it('has many work items', function () {
    $plan = PerformancePlan::factory()->create();
    WorkItem::factory()->count(3)->create(['performance_plan_id' => $plan->id]);

    expect($plan->workItems)->toHaveCount(3);
});

it('casts target to decimal string', function () {
    $plan = PerformancePlan::factory()->create(['target' => 5.00]);

    expect((float) $plan->target)->toBe(5.0);
});

it('casts period to integer', function () {
    $plan = PerformancePlan::factory()->create(['period' => 2]);

    expect($plan->period)->toBe(2);
});
