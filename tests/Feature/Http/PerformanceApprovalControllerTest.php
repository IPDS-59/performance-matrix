<?php

use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\PerformanceReportReview;
use App\Models\Project;
use App\Models\WorkItem;
use App\Models\WorkItemAssignment;
use Illuminate\Support\Facades\Notification;

function makeReportForApproval(): array
{
    $leadUser = staffUser();
    $leadEmployee = Employee::factory()->create(['user_id' => $leadUser->id]);

    $memberUser = staffUser();
    $memberEmployee = Employee::factory()->create(['user_id' => $memberUser->id]);

    $project = Project::factory()->create(['leader_id' => $leadEmployee->id]);
    $project->members()->attach($memberEmployee->id, ['role' => 'member']);

    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    WorkItemAssignment::create([
        'work_item_id' => $workItem->id,
        'employee_id' => $memberEmployee->id,
        'target' => 10,
        'target_unit' => 'Kegiatan',
    ]);

    $report = PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $memberEmployee->id,
        'period_month' => 4,
        'period_year' => 2026,
        'realization' => 5,
        'approval_status' => 'pending',
    ]);

    return compact('leadUser', 'memberUser', 'report');
}

it('creates an approved review record when lead approves', function () {
    Notification::fake();

    ['leadUser' => $leadUser, 'report' => $report] = makeReportForApproval();

    $this->actingAs($leadUser)
        ->patch(route('performance.approve', $report), ['review_note' => 'Bagus'])
        ->assertRedirect();

    expect($report->fresh()->approval_status)->toBe('approved');

    $review = PerformanceReportReview::where('performance_report_id', $report->id)->sole();
    expect($review->action)->toBe('approved');
    expect($review->note)->toBe('Bagus');
    expect($review->actor_id)->toBe($leadUser->id);
});

it('creates a rejected review record when lead rejects', function () {
    Notification::fake();

    ['leadUser' => $leadUser, 'report' => $report] = makeReportForApproval();

    $this->actingAs($leadUser)
        ->patch(route('performance.reject', $report), ['review_note' => 'Data kurang lengkap'])
        ->assertRedirect();

    expect($report->fresh()->approval_status)->toBe('rejected');

    $review = PerformanceReportReview::where('performance_report_id', $report->id)->sole();
    expect($review->action)->toBe('rejected');
    expect($review->note)->toBe('Data kurang lengkap');
    expect($review->actor_id)->toBe($leadUser->id);
});

it('forbids non-lead from approving a report', function () {
    Notification::fake();

    ['memberUser' => $memberUser, 'report' => $report] = makeReportForApproval();

    $this->actingAs($memberUser)
        ->patch(route('performance.approve', $report))
        ->assertForbidden();

    expect(PerformanceReportReview::where('performance_report_id', $report->id)->count())->toBe(0);
});
