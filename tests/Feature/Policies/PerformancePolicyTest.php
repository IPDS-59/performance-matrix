<?php

use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Policies\PerformancePolicy;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::create(['name' => 'enter-performance', 'guard_name' => 'web']);
    Role::create(['name' => 'staff', 'guard_name' => 'web'])->givePermissionTo('enter-performance');
    Role::create(['name' => 'head', 'guard_name' => 'web']);
});

it('allows staff to create performance reports', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');

    expect((new PerformancePolicy)->create($user))->toBeTrue();
});

it('denies non-staff to create performance reports', function () {
    $user = User::factory()->create();
    $user->assignRole('head');

    expect((new PerformancePolicy)->create($user))->toBeFalse();
});

it('allows staff to store report for their own project work item', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $project = Project::factory()->create();
    $project->members()->attach($employee->id, ['role' => 'member']);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    expect((new PerformancePolicy)->store($user, $workItem))->toBeTrue();
});

it('denies staff from storing report for a work item they are not assigned to', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $otherProject = Project::factory()->create();
    $workItem = WorkItem::factory()->create(['project_id' => $otherProject->id]);

    expect((new PerformancePolicy)->store($user, $workItem))->toBeFalse();
});

it('denies store when user has no linked employee', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');
    $workItem = WorkItem::factory()->create();

    expect((new PerformancePolicy)->store($user, $workItem))->toBeFalse();
});

it('allows staff to update their own report', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $project = Project::factory()->create();
    $project->members()->attach($employee->id, ['role' => 'member']);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);
    $report = PerformanceReport::factory()->create(['work_item_id' => $workItem->id]);

    expect((new PerformancePolicy)->update($user, $report))->toBeTrue();
});

it('denies staff from updating a report for a project they are not in', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $otherProject = Project::factory()->create();
    $workItem = WorkItem::factory()->create(['project_id' => $otherProject->id]);
    $report = PerformanceReport::factory()->create(['work_item_id' => $workItem->id]);

    expect((new PerformancePolicy)->update($user, $report))->toBeFalse();
});

it('allows staff to delete their own non-approved report', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $workItem = WorkItem::factory()->create();
    $report = PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $employee->id,
        'approval_status' => 'pending',
    ]);

    expect((new PerformancePolicy)->delete($user, $report))->toBeTrue();
});

it('denies deleting an approved report', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $workItem = WorkItem::factory()->create();
    $report = PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $employee->id,
        'approval_status' => 'approved',
    ]);

    expect((new PerformancePolicy)->delete($user, $report))->toBeFalse();
});

it('denies deleting another employee\'s report', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $otherEmployee = Employee::factory()->create();
    $workItem = WorkItem::factory()->create();
    $report = PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $otherEmployee->id,
        'approval_status' => 'pending',
    ]);

    expect((new PerformancePolicy)->delete($user, $report))->toBeFalse();
});
