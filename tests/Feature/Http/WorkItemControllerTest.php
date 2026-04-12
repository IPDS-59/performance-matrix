<?php

use App\Models\Employee;
use App\Models\Project;
use App\Models\WorkItem;
use App\Models\WorkItemAssignment;

it('stores a work item assigned to all project members', function () {
    $members = Employee::factory()->count(2)->create(['is_active' => true]);
    $project = Project::factory()->create();
    $project->members()->attach($members->pluck('id'), ['role' => 'member']);

    $this->actingAs(adminUser())
        ->post(route('work-items.store', $project), [
            'number' => 1,
            'description' => 'Persiapan lapangan',
            'target' => 5,
            'target_unit' => 'Dokumen',
            'assign_to' => 'all',
        ])
        ->assertRedirect();

    $workItem = WorkItem::where('project_id', $project->id)->where('number', 1)->firstOrFail();
    expect(WorkItemAssignment::where('work_item_id', $workItem->id)->count())->toBe(2);
});

it('stores a work item assigned to specific members', function () {
    $members = Employee::factory()->count(3)->create(['is_active' => true]);
    $project = Project::factory()->create();
    $project->members()->attach($members->pluck('id'), ['role' => 'member']);

    $assigned = $members->take(2);

    $this->actingAs(adminUser())
        ->post(route('work-items.store', $project), [
            'number' => 1,
            'description' => 'Kegiatan tertentu',
            'target' => 1,
            'target_unit' => 'Kegiatan',
            'assign_to' => 'specific',
            'assignments' => $assigned->map(fn ($m) => [
                'employee_id' => $m->id,
                'target' => 3,
                'target_unit' => 'Laporan',
            ])->all(),
        ])
        ->assertRedirect();

    $workItem = WorkItem::where('project_id', $project->id)->where('number', 1)->firstOrFail();
    expect(WorkItemAssignment::where('work_item_id', $workItem->id)->count())->toBe(2);
});

it('validates required fields on store', function () {
    $project = Project::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('work-items.store', $project), [])
        ->assertSessionHasErrors(['number', 'description', 'target', 'target_unit', 'assign_to']);
});

it('denies work item store for staff', function () {
    $project = Project::factory()->create();

    $this->actingAs(staffUser())
        ->post(route('work-items.store', $project), [
            'number' => 1,
            'description' => 'Test',
            'target' => 1,
            'target_unit' => 'Kegiatan',
            'assign_to' => 'all',
        ])
        ->assertForbidden();
});

it('updates a work item and re-syncs assignments', function () {
    $members = Employee::factory()->count(2)->create(['is_active' => true]);
    $project = Project::factory()->create();
    $project->members()->attach($members->pluck('id'), ['role' => 'member']);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);
    // Load relationship so the controller can resolve project->members
    $workItem->load('project');

    $this->actingAs(adminUser())
        ->put(route('work-items.update', $workItem), [
            'description' => 'Updated description',
            'target' => 3,
            'target_unit' => 'Laporan',
            'assign_to' => 'all',
        ])
        ->assertRedirect();

    expect($workItem->fresh()->description)->toBe('Updated description');
    expect(WorkItemAssignment::where('work_item_id', $workItem->id)->count())->toBe(2);
});

it('deletes a work item', function () {
    $workItem = WorkItem::factory()->create();

    $this->actingAs(adminUser())
        ->delete(route('work-items.destroy', $workItem))
        ->assertRedirect();

    expect(WorkItem::find($workItem->id))->toBeNull();
});

it('redirects guests to login on store', function () {
    $project = Project::factory()->create();

    $this->post(route('work-items.store', $project), [
        'number' => 1, 'description' => 'test',
        'target' => 1, 'target_unit' => 'Kegiatan', 'assign_to' => 'all',
    ])->assertRedirect(route('login'));
});
