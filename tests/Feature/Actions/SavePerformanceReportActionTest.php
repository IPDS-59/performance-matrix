<?php

use App\Actions\Performance\SavePerformanceReportAction;
use App\Events\PerformanceReportSaved;
use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Event;

it('creates a performance report with computed achievement percentage', function () {
    Event::fake();

    // target=4 so realization=3 => 75%
    $workItem = WorkItem::factory()->create(['target' => 4, 'target_unit' => 'Kegiatan']);
    $employee = Employee::factory()->create();
    $action = app(SavePerformanceReportAction::class);

    $report = $action->execute(
        workItem: $workItem,
        periodMonth: 4,
        periodYear: 2026,
        realization: 3,
        reporter: $employee,
        issues: 'Some issues',
        solutions: 'Some solutions',
        actionPlan: 'Next steps',
    );

    expect($report)->toBeInstanceOf(PerformanceReport::class);
    expect((float) $report->realization)->toBe(3.0);
    expect((float) $report->achievement_percentage)->toBe(75.0);
    expect($report->period_month)->toBe(4);
    expect($report->period_year)->toBe(2026);
    expect($report->reported_by)->toBe($employee->id);
    expect($report->issues)->toBe('Some issues');

    Event::assertDispatched(PerformanceReportSaved::class);
});

it('updates an existing report for the same work item and period', function () {
    Event::fake();

    // target=1 so realization=1 => 100%
    $workItem = WorkItem::factory()->create(['target' => 1, 'target_unit' => 'Kegiatan']);
    $action = app(SavePerformanceReportAction::class);

    $action->execute($workItem, 4, 2026, 0.0);
    $action->execute($workItem, 4, 2026, 1.0);

    expect(PerformanceReport::where('work_item_id', $workItem->id)->count())->toBe(1);
    expect((float) PerformanceReport::where('work_item_id', $workItem->id)->value('achievement_percentage'))->toBe(100.0);
});

it('creates separate reports for different periods', function () {
    Event::fake();

    $workItem = WorkItem::factory()->create(['target' => 1]);
    $action = app(SavePerformanceReportAction::class);

    $action->execute($workItem, 3, 2026, 0.5);
    $action->execute($workItem, 4, 2026, 1.0);

    expect(PerformanceReport::where('work_item_id', $workItem->id)->count())->toBe(2);
});

it('caps achievement at 100 when realization exceeds target', function () {
    Event::fake();

    $workItem = WorkItem::factory()->create(['target' => 1]);
    $action = app(SavePerformanceReportAction::class);

    $report = $action->execute($workItem, 4, 2026, 2.0);

    expect((float) $report->achievement_percentage)->toBe(100.0);
});
