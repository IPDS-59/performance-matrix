<?php

use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\Project;
use App\Models\Team;
use App\Models\WorkItem;

it('belongs to a team', function () {
    $project = Project::factory()->create();

    expect($project->team)->toBeInstanceOf(Team::class);
});

it('has many work items', function () {
    $project = Project::factory()->create();
    WorkItem::factory()->count(3)->create(['project_id' => $project->id]);

    expect($project->workItems)->toHaveCount(3);
});

it('has members via pivot', function () {
    $project = Project::factory()->create();
    $employees = Employee::factory()->count(2)->create();
    $project->members()->attach($employees->pluck('id'), ['role' => 'member']);

    expect($project->members)->toHaveCount(2);
    expect($project->members->first()->pivot->role)->toBe('member');
});

it('has performance reports through work items', function () {
    $project = Project::factory()->create();
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);
    PerformanceReport::factory()->count(2)->create(['work_item_id' => $workItem->id]);

    expect($project->performanceReports)->toHaveCount(2);
});

it('scopes to active status', function () {
    Project::factory()->create(['status' => 'active']);
    Project::factory()->create(['status' => 'completed']);
    Project::factory()->create(['status' => 'cancelled']);

    expect(Project::where('status', 'active')->count())->toBe(1);
});
