<?php

use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\Project;
use App\Models\WorkItem;
use App\Models\WorkItemAssignment;

it('redirects guests to login', function () {
    $project = Project::factory()->create();
    $this->get(route('performance.projects.show', $project))->assertRedirect(route('login'));
});

it('forbids access for non-staff', function () {
    $project = Project::factory()->create();
    $this->actingAs(adminUser())
        ->get(route('performance.projects.show', $project))
        ->assertForbidden();
});

it('forbids access when user has no linked employee', function () {
    $project = Project::factory()->create();
    $this->actingAs(staffUser())
        ->get(route('performance.projects.show', $project))
        ->assertForbidden();
});

it('forbids member of another project from viewing', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(); // employee is NOT a member

    $this->actingAs($user)
        ->get(route('performance.projects.show', $project))
        ->assertForbidden();
});

it('renders ProjectDetail for a project member (employee view)', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['year' => now()->year]);
    $project->members()->attach($employee->id, ['role' => 'member']);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);
    WorkItemAssignment::create([
        'work_item_id' => $workItem->id,
        'employee_id' => $employee->id,
        'target' => 10,
        'target_unit' => 'Kegiatan',
    ]);

    $this->actingAs($user)
        ->get(route('performance.projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->component('Performance/ProjectDetail')
            ->where('is_lead', false)
            ->has('project')
            ->has('work_items', 1)
            ->has('work_items.0', fn ($wi) => $wi
                ->where('id', $workItem->id)
                ->where('target', 10)
                ->where('target_unit', 'Kegiatan')
                ->where('year_realization', 0)
                ->where('year_pct', 0)
                ->where('report_count', 0)
                ->where('has_pending', false)
                ->where('has_rejected', false)
                ->where('all_approved', false)
                ->etc()
            )
        );
});

it('reflects submitted reports in employee work item status', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create(['year' => now()->year]);
    $project->members()->attach($employee->id, ['role' => 'member']);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);
    WorkItemAssignment::create([
        'work_item_id' => $workItem->id,
        'employee_id' => $employee->id,
        'target' => 10,
        'target_unit' => 'Kegiatan',
    ]);
    PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $employee->id,
        'period_year' => now()->year,
        'period_month' => 3,
        'realization' => 5,
        'approval_status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('performance.projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->has('work_items.0', fn ($wi) => $wi
                ->where('year_realization', 5)
                ->where('year_pct', 50)
                ->where('report_count', 1)
                ->where('has_pending', true)
                ->where('has_rejected', false)
                ->etc()
            )
        );
});

it('renders ProjectDetail for the project lead (lead view)', function () {
    $leadUser = staffUser();
    $leadEmployee = Employee::factory()->create(['user_id' => $leadUser->id]);
    $memberEmployee = Employee::factory()->create();

    $project = Project::factory()->create([
        'year' => now()->year,
        'leader_id' => $leadEmployee->id,
    ]);
    $project->members()->attach($memberEmployee->id, ['role' => 'member']);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);
    WorkItemAssignment::create([
        'work_item_id' => $workItem->id,
        'employee_id' => $memberEmployee->id,
        'target' => 10,
        'target_unit' => 'Kegiatan',
    ]);
    PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $memberEmployee->id,
        'period_year' => now()->year,
        'approval_status' => 'pending',
    ]);

    $this->actingAs($leadUser)
        ->get(route('performance.projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->component('Performance/ProjectDetail')
            ->where('is_lead', true)
            ->has('work_items', 1)
            ->has('work_items.0', fn ($wi) => $wi
                ->where('id', $workItem->id)
                ->where('pending_count', 1)
                ->where('approved_count', 0)
                ->where('rejected_count', 0)
                ->where('total_report_count', 1)
                ->has('assigned_members', 1)
                ->etc()
            )
        );
});

it('scopes employee view to only their assigned work items', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $otherEmployee = Employee::factory()->create();

    $project = Project::factory()->create(['year' => now()->year]);
    $project->members()->attach([$employee->id => ['role' => 'member'], $otherEmployee->id => ['role' => 'member']]);

    // Two work items: one assigned to our employee, one to the other
    $myItem = WorkItem::factory()->create(['project_id' => $project->id, 'number' => 1]);
    $otherItem = WorkItem::factory()->create(['project_id' => $project->id, 'number' => 2]);
    WorkItemAssignment::create(['work_item_id' => $myItem->id, 'employee_id' => $employee->id, 'target' => 5, 'target_unit' => 'Keg']);
    WorkItemAssignment::create(['work_item_id' => $otherItem->id, 'employee_id' => $otherEmployee->id, 'target' => 5, 'target_unit' => 'Keg']);

    $this->actingAs($user)
        ->get(route('performance.projects.show', $project))
        ->assertInertia(fn ($page) => $page
            ->has('work_items', 1)
            ->where('work_items.0.id', $myItem->id)
        );
});
