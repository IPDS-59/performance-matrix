<?php

namespace App\Actions\Performance;

use App\Events\PerformanceReportSaved;
use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\WorkItem;

class SavePerformanceReportAction
{
    public function execute(
        WorkItem $workItem,
        int $periodMonth,
        int $periodYear,
        float $realization,
        ?Employee $reporter = null,
        ?string $issues = null,
        ?string $solutions = null,
        ?string $actionPlan = null,
    ): PerformanceReport {
        // Use the per-employee assignment target when available
        $assignment = $reporter
            ? $workItem->assignments()->where('employee_id', $reporter->id)->first()
            : null;

        $target = $assignment
            ? (float) $assignment->target
            : (float) $workItem->target;

        $achievementPercentage = $target > 0
            ? min(100, round(($realization / $target) * 100, 2))
            : 0;

        $report = PerformanceReport::updateOrCreate(
            [
                'work_item_id' => $workItem->id,
                'reported_by' => $reporter?->id,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
            ],
            [
                'realization' => $realization,
                'achievement_percentage' => $achievementPercentage,
                'issues' => $issues,
                'solutions' => $solutions,
                'action_plan' => $actionPlan,
            ]
        );

        PerformanceReportSaved::dispatch($report);

        return $report;
    }
}
