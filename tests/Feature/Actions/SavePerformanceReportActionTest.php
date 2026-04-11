<?php

use App\Actions\Performance\SavePerformanceReportAction;
use App\Events\PerformanceReportSaved;
use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Event;

it('creates a performance report', function () {
    Event::fake();

    $workItem = WorkItem::factory()->create();
    $employee = Employee::factory()->create();
    $action = app(SavePerformanceReportAction::class);

    $report = $action->execute(
        workItem: $workItem,
        periodMonth: 4,
        periodYear: 2026,
        achievementPercentage: 75.5,
        reporter: $employee,
        issues: 'Some issues',
        solutions: 'Some solutions',
        actionPlan: 'Next steps',
    );

    expect($report)->toBeInstanceOf(PerformanceReport::class);
    expect((float) $report->achievement_percentage)->toBe(75.5);
    expect($report->period_month)->toBe(4);
    expect($report->period_year)->toBe(2026);
    expect($report->reported_by)->toBe($employee->id);
    expect($report->issues)->toBe('Some issues');

    Event::assertDispatched(PerformanceReportSaved::class);
});

it('updates an existing report for the same work item and period', function () {
    Event::fake();

    $workItem = WorkItem::factory()->create();
    $action = app(SavePerformanceReportAction::class);

    $action->execute($workItem, 4, 2026, 50.0);
    $action->execute($workItem, 4, 2026, 80.0);

    expect(PerformanceReport::where('work_item_id', $workItem->id)->count())->toBe(1);
    expect((float) PerformanceReport::where('work_item_id', $workItem->id)->value('achievement_percentage'))->toBe(80.0);
});

it('creates separate reports for different periods', function () {
    Event::fake();

    $workItem = WorkItem::factory()->create();
    $action = app(SavePerformanceReportAction::class);

    $action->execute($workItem, 3, 2026, 60.0);
    $action->execute($workItem, 4, 2026, 70.0);

    expect(PerformanceReport::where('work_item_id', $workItem->id)->count())->toBe(2);
});
