<?php

use App\Models\Project;
use App\Models\WorkItem;

it('stores a work item under a project', function () {
    $project = Project::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('work-items.store', $project), [
            'number' => 1,
            'description' => 'Persiapan lapangan',
        ])
        ->assertRedirect();

    expect(WorkItem::where('project_id', $project->id)->where('number', 1)->exists())->toBeTrue();
});

it('validates required fields on store', function () {
    $project = Project::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('work-items.store', $project), [])
        ->assertSessionHasErrors(['number', 'description']);
});

it('denies work item store for staff', function () {
    $project = Project::factory()->create();

    $this->actingAs(staffUser())
        ->post(route('work-items.store', $project), [
            'number' => 1,
            'description' => 'Test',
        ])
        ->assertForbidden();
});

it('updates a work item description', function () {
    $workItem = WorkItem::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('work-items.update', $workItem), [
            'description' => 'Updated description',
        ])
        ->assertRedirect();

    expect($workItem->fresh()->description)->toBe('Updated description');
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

    $this->post(route('work-items.store', $project), ['number' => 1, 'description' => 'test'])
        ->assertRedirect(route('login'));
});
