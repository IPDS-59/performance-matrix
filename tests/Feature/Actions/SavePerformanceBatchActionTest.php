<?php

use App\Actions\Performance\SavePerformanceBatchAction;
use App\Events\PerformanceBatchSubmitted;
use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;

it('saves multiple reports in a batch', function () {
    Event::fake();

    $employee = Employee::factory()->create();
    $items = WorkItem::factory()->count(3)->create(['target' => 1]);
    $action = app(SavePerformanceBatchAction::class);

    $reports = $action->execute(
        reporter: $employee,
        periodMonth: 4,
        periodYear: 2026,
        items: $items->map(fn ($wi) => [
            'work_item_id' => $wi->id,
            'realization' => 1.0,
            'issues' => null,
            'solutions' => null,
            'action_plan' => null,
        ])->all(),
    );

    expect($reports)->toHaveCount(3);
    expect(PerformanceReport::count())->toBe(3);
    Event::assertDispatched(PerformanceBatchSubmitted::class);
});

it('rolls back all reports if one fails', function () {
    Event::fake();

    $employee = Employee::factory()->create();
    $action = app(SavePerformanceBatchAction::class);

    expect(fn () => $action->execute(
        reporter: $employee,
        periodMonth: 4,
        periodYear: 2026,
        items: [
            ['work_item_id' => 99999, 'realization' => 1.0, 'issues' => null, 'solutions' => null, 'action_plan' => null],
        ],
    ))->toThrow(ModelNotFoundException::class);

    expect(PerformanceReport::count())->toBe(0);
});

it('dispatches batch event with correct reporter and period', function () {
    Event::fake();

    $employee = Employee::factory()->create();
    $workItem = WorkItem::factory()->create(['target' => 1]);
    $action = app(SavePerformanceBatchAction::class);

    $action->execute(
        reporter: $employee,
        periodMonth: 5,
        periodYear: 2026,
        items: [
            ['work_item_id' => $workItem->id, 'realization' => 1.0, 'issues' => null, 'solutions' => null, 'action_plan' => null],
        ],
    );

    Event::assertDispatched(PerformanceBatchSubmitted::class, function ($event) use ($employee) {
        return $event->reporter->id === $employee->id
            && $event->periodMonth === 5
            && $event->periodYear === 2026;
    });
});
