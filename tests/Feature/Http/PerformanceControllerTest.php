<?php

use App\Events\PerformanceBatchSubmitted;
use App\Models\Employee;
use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Event;

it('stores a batch of performance reports', function () {
    Event::fake();

    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $project = Project::factory()->create();
    $project->members()->attach($employee->id, ['role' => 'member']);
    $workItem = WorkItem::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->post(route('performance.batch'), [
            'period_month' => 4,
            'period_year' => 2026,
            'items' => [
                [
                    'work_item_id' => $workItem->id,
                    'realization' => 1,
                    'issues' => null,
                    'solutions' => null,
                    'action_plan' => null,
                ],
            ],
        ])
        ->assertRedirect();

    Event::assertDispatched(PerformanceBatchSubmitted::class);
});

it('validates required fields on batch store', function () {
    $user = staffUser();
    Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('performance.batch'), [])
        ->assertSessionHasErrors(['period_month', 'period_year', 'items']);

});
