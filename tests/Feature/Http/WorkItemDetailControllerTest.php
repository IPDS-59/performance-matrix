<?php

use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\PerformanceReportReview;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemAssignment;

it('redirects guests to login', function () {
    $workItem = WorkItem::factory()->create();
    $this->get(route('performance.work-items.show', $workItem))->assertRedirect(route('login'));
});

it('forbids non-staff from viewing', function () {
    $workItem = WorkItem::factory()->create();
    $this->actingAs(adminUser())
        ->get(route('performance.work-items.show', $workItem))
        ->assertForbidden();
});

it('forbids unassigned employee from viewing', function () {
    $user = staffUser();
    Employee::factory()->create(['user_id' => $user->id]);
    $workItem = WorkItem::factory()->create();

    $this->actingAs($user)
        ->get(route('performance.work-items.show', $workItem))
        ->assertForbidden();
});

it('renders WorkItemDetail for an assigned employee', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['year' => now()->year]);
    $project->members()->attach($employee->id, ['role' => 'member']);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);
    WorkItemAssignment::create([
        'work_item_id' => $workItem->id,
        'employee_id' => $employee->id,
        'target' => 12,
        'target_unit' => 'Laporan',
    ]);

    $this->actingAs($user)
        ->get(route('performance.work-items.show', $workItem))
        ->assertInertia(fn ($page) => $page
            ->component('Performance/WorkItemDetail')
            ->where('is_lead', false)
            ->has('work_item')
            ->where('work_item.target', 12)
            ->where('work_item.target_unit', 'Laporan')
            ->has('reports', 0)
            ->where('member_reports', null)
            ->where('year', now()->year)
        );
});

it('includes report reviews in employee view', function () {
    $user = staffUser();
    $actorUser = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['year' => now()->year]);
    $project->members()->attach($employee->id, ['role' => 'member']);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);
    WorkItemAssignment::create([
        'work_item_id' => $workItem->id, 'employee_id' => $employee->id, 'target' => 12, 'target_unit' => 'Keg',
    ]);
    $report = PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $employee->id,
        'period_year' => now()->year,
        'period_month' => 3,
        'approval_status' => 'rejected',
    ]);
    PerformanceReportReview::create([
        'performance_report_id' => $report->id,
        'actor_id' => $user->id,
        'action' => 'submitted',
    ]);
    PerformanceReportReview::create([
        'performance_report_id' => $report->id,
        'actor_id' => $actorUser->id,
        'action' => 'rejected',
        'note' => 'Data kurang',
    ]);

    $this->actingAs($user)
        ->get(route('performance.work-items.show', $workItem))
        ->assertInertia(fn ($page) => $page
            ->has('reports', 1)
            ->has('reports.0.reviews', 2)
            ->where('reports.0.reviews.0.action', 'submitted')
            ->where('reports.0.reviews.1.action', 'rejected')
            ->where('reports.0.reviews.1.note', 'Data kurang')
            ->where('reports.0.approval_status', 'rejected')
        );
});

it('renders WorkItemDetail for the project lead', function () {
    $leadUser = staffUser();
    $leadEmployee = Employee::factory()->create(['user_id' => $leadUser->id]);
    $memberEmployee = Employee::factory()->create();

    $project = Project::factory()->create(['year' => now()->year, 'leader_id' => $leadEmployee->id]);
    $project->members()->attach($memberEmployee->id, ['role' => 'member']);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);
    WorkItemAssignment::create([
        'work_item_id' => $workItem->id, 'employee_id' => $memberEmployee->id, 'target' => 10, 'target_unit' => 'Keg',
    ]);

    $this->actingAs($leadUser)
        ->get(route('performance.work-items.show', $workItem))
        ->assertInertia(fn ($page) => $page
            ->component('Performance/WorkItemDetail')
            ->where('is_lead', true)
            ->where('reports', null)
            ->has('member_reports', 1)
            ->where('member_reports.0.employee.id', $memberEmployee->id)
            ->has('member_reports.0.reports', 0)
        );
});
