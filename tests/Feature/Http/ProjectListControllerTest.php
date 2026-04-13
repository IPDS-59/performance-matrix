<?php

use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\Project;
use App\Models\WorkItem;
use App\Models\WorkItemAssignment;

it('redirects guests to login', function () {
    $this->get(route('performance.index'))->assertRedirect(route('login'));
});

it('forbids access for non-staff', function () {
    $this->actingAs(adminUser())
        ->get(route('performance.index'))
        ->assertForbidden();
});

it('forbids access when staff has no linked employee', function () {
    $this->actingAs(staffUser())
        ->get(route('performance.index'))
        ->assertForbidden();
});

it('renders ProjectList for linked staff', function () {
    $user = staffUser();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('performance.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Performance/ProjectList')
            ->has('employee')
            ->has('employee_projects')
            ->has('lead_projects')
            ->has('is_team_lead')
            ->has('filters')
            ->where('filters.year', now()->year)
        );
});

it('returns employee project counts without loading work items or reports', function () {
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

    $response = $this->actingAs($user)
        ->get(route('performance.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Performance/ProjectList')
            ->has('employee_projects', 1)
            ->has('employee_projects.0', fn ($p) => $p
                ->where('id', $project->id)
                ->where('assigned_items_count', 1)
                ->where('submitted_items_count', 0)
                ->where('pending_review_count', 0)
                ->where('rejected_count', 0)
                ->etc()
            )
        );
});

it('counts submitted items correctly for employee', function () {
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
        'approval_status' => 'pending',
    ]);

    $this->actingAs($user)
        ->get(route('performance.index'))
        ->assertInertia(fn ($page) => $page
            ->has('employee_projects.0', fn ($p) => $p
                ->where('submitted_items_count', 1)
                ->where('pending_review_count', 1)
                ->where('rejected_count', 0)
                ->etc()
            )
        );
});

it('returns lead projects with pending review counts', function () {
    $leadUser = staffUser();
    $leadEmployee = Employee::factory()->create(['user_id' => $leadUser->id]);

    $memberEmployee = Employee::factory()->create();

    $project = Project::factory()->create([
        'year' => now()->year,
        'leader_id' => $leadEmployee->id,
    ]);
    $project->members()->attach($memberEmployee->id, ['role' => 'member']);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    PerformanceReport::factory()->create([
        'work_item_id' => $workItem->id,
        'reported_by' => $memberEmployee->id,
        'period_year' => now()->year,
        'approval_status' => 'pending',
    ]);

    $this->actingAs($leadUser)
        ->get(route('performance.index'))
        ->assertInertia(fn ($page) => $page
            ->where('is_team_lead', true)
            ->has('lead_projects', 1)
            ->has('lead_projects.0', fn ($p) => $p
                ->where('id', $project->id)
                ->where('pending_reviews_count', 1)
                ->where('work_items_count', 1)
                ->where('members_count', 1)
                ->etc()
            )
        );
});

it('returns empty lead_projects for non-leads', function () {
    $user = staffUser();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('performance.index'))
        ->assertInertia(fn ($page) => $page
            ->where('is_team_lead', false)
            ->has('lead_projects', 0)
        );
});
