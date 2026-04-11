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
        float $achievementPercentage,
        ?Employee $reporter = null,
        ?string $issues = null,
        ?string $solutions = null,
        ?string $actionPlan = null,
    ): PerformanceReport {
        $report = PerformanceReport::updateOrCreate(
            [
                'work_item_id' => $workItem->id,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
            ],
            [
                'reported_by' => $reporter?->id,
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
