<?php

use App\Events\PerformanceBatchSubmitted;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\ReportSubmittedNotification;
use Illuminate\Support\Facades\Notification;

it('notifies the project lead when a member submits reports', function () {
    Notification::fake();

    $leadUser = User::factory()->create();
    $leadEmployee = Employee::factory()->create(['user_id' => $leadUser->id]);
    $memberEmployee = Employee::factory()->create();

    $project = Project::factory()->create([
        'year' => 2026,
        'leader_id' => $leadEmployee->id,
    ]);
    $project->members()->attach($memberEmployee->id, ['role' => 'member']);

    WorkItem::factory()->create(['project_id' => $project->id]);

    PerformanceBatchSubmitted::dispatch(
        reporter: $memberEmployee,
        periodMonth: 3,
        periodYear: 2026,
        reportIds: [1],
    );

    Notification::assertSentTo($leadUser, ReportSubmittedNotification::class);
});

it('does not notify a lead when they submit their own report', function () {
    Notification::fake();

    $leadUser = User::factory()->create();
    $leadEmployee = Employee::factory()->create(['user_id' => $leadUser->id]);

    $project = Project::factory()->create([
        'year' => 2026,
        'leader_id' => $leadEmployee->id,
    ]);
    $project->members()->attach($leadEmployee->id, ['role' => 'leader']);

    PerformanceBatchSubmitted::dispatch(
        reporter: $leadEmployee,
        periodMonth: 3,
        periodYear: 2026,
        reportIds: [1],
    );

    Notification::assertNothingSent();
});

it('does not notify when project year does not match period year', function () {
    Notification::fake();

    $leadUser = User::factory()->create();
    $leadEmployee = Employee::factory()->create(['user_id' => $leadUser->id]);
    $memberEmployee = Employee::factory()->create();

    $project = Project::factory()->create([
        'year' => 2025,
        'leader_id' => $leadEmployee->id,
    ]);
    $project->members()->attach($memberEmployee->id, ['role' => 'member']);

    PerformanceBatchSubmitted::dispatch(
        reporter: $memberEmployee,
        periodMonth: 3,
        periodYear: 2026, // different from project year
        reportIds: [1],
    );

    Notification::assertNothingSent();
});

it('does not notify when the lead has no user account', function () {
    Notification::fake();

    $leadEmployee = Employee::factory()->create(['user_id' => null]);
    $memberEmployee = Employee::factory()->create();

    $project = Project::factory()->create([
        'year' => 2026,
        'leader_id' => $leadEmployee->id,
    ]);
    $project->members()->attach($memberEmployee->id, ['role' => 'member']);

    PerformanceBatchSubmitted::dispatch(
        reporter: $memberEmployee,
        periodMonth: 3,
        periodYear: 2026,
        reportIds: [1],
    );

    Notification::assertNothingSent();
});
