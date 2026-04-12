<?php

use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\PerformanceReportReview;
use App\Models\Project;
use App\Models\WorkItem;
use App\Models\WorkItemAssignment;
use Illuminate\Support\Facades\Notification;

it('allows employee to resubmit a rejected report', function () {
    Notification::fake();

    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create();
    $workItem = WorkItem::factory()->create(['project_id' => $project->id, 'target' => 10]);
    WorkItemAssignment::create([
        'work_item_id' => $workItem->id, 'employee_id' => $employee->id, 'target' => 10, 'target_unit' => 'Keg',
    ]);
    $report = PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $employee->id,
        'realization' => 2,
        'approval_status' => 'rejected',
        'review_note' => 'Data kurang',
    ]);

    $this->actingAs($user)
        ->patch(route('performance.resubmit', $report), [
            'realization' => 5,
            'issues' => 'Terjadi hambatan',
            'solutions' => 'Sudah diatasi',
            'action_plan' => 'Lanjutkan',
        ])
        ->assertRedirect();

    $report->refresh();
    expect($report->approval_status)->toBe('pending');
    expect((float) $report->realization)->toBe(5.0);
    expect($report->review_note)->toBeNull();
    expect($report->reviewed_by)->toBeNull();
    expect($report->reviewed_at)->toBeNull();
    expect($report->issues)->toBe('Terjadi hambatan');

    $review = PerformanceReportReview::where('performance_report_id', $report->id)
        ->where('action', 'resubmitted')
        ->first();
    expect($review)->not->toBeNull();
    expect($review->actor_id)->toBe($user->id);
});

it('forbids resubmitting a pending report', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create();
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);
    $report = PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $employee->id,
        'approval_status' => 'pending',
    ]);

    $this->actingAs($user)
        ->patch(route('performance.resubmit', $report), ['realization' => 3])
        ->assertStatus(422);

    expect(PerformanceReportReview::where('performance_report_id', $report->id)->where('action', 'resubmitted')->count())->toBe(0);
});

it('forbids resubmitting another employee report', function () {
    $user = staffUser();
    Employee::factory()->create(['user_id' => $user->id]);

    $otherEmployee = Employee::factory()->create();
    $project = Project::factory()->create();
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);
    $report = PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $otherEmployee->id,
        'approval_status' => 'rejected',
    ]);

    $this->actingAs($user)
        ->patch(route('performance.resubmit', $report), ['realization' => 3])
        ->assertForbidden();
});

it('notifies the team lead when a report is resubmitted', function () {
    Notification::fake();

    $leadUser = \App\Models\User::factory()->create();
    $leadEmployee = Employee::factory()->create(['user_id' => $leadUser->id]);

    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $project = Project::factory()->create(['year' => 2026, 'leader_id' => $leadEmployee->id]);
    $project->members()->attach($employee->id, ['role' => 'member']);

    $workItem = WorkItem::factory()->create(['project_id' => $project->id, 'target' => 10]);
    WorkItemAssignment::create([
        'work_item_id' => $workItem->id, 'employee_id' => $employee->id, 'target' => 10, 'target_unit' => 'Keg',
    ]);

    $report = PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $employee->id,
        'period_year' => 2026,
        'period_month' => 3,
        'approval_status' => 'rejected',
    ]);

    $this->actingAs($user)
        ->patch(route('performance.resubmit', $report), ['realization' => 5])
        ->assertRedirect();

    Notification::assertSentTo($leadUser, \App\Notifications\ReportSubmittedNotification::class);
});

it('recalculates achievement percentage on resubmit', function () {
    Notification::fake();

    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create();
    $workItem = WorkItem::factory()->create(['project_id' => $project->id, 'target' => 12]);
    WorkItemAssignment::create([
        'work_item_id' => $workItem->id, 'employee_id' => $employee->id, 'target' => 12, 'target_unit' => 'Keg',
    ]);
    // Existing approved report for month 1
    PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $employee->id,
        'period_year' => 2026,
        'period_month' => 1,
        'realization' => 2,
        'approval_status' => 'approved',
    ]);
    // Rejected report for month 2
    $rejected = PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $employee->id,
        'period_year' => 2026,
        'period_month' => 2,
        'realization' => 1,
        'approval_status' => 'rejected',
    ]);

    $this->actingAs($user)
        ->patch(route('performance.resubmit', $rejected), ['realization' => 4])
        ->assertRedirect();

    // month 1: 2 + month 2 resubmit: 4 = 6 / target 12 = 50%
    $rejected->refresh();
    expect((float) $rejected->achievement_percentage)->toBe(50.0);
});
