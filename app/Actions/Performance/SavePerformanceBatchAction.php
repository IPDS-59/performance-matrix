<?php

namespace App\Actions\Performance;

use App\Events\PerformanceBatchSubmitted;
use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\WorkItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SavePerformanceBatchAction
{
    public function __construct(
        private readonly SavePerformanceReportAction $saveReport,
    ) {}

    /**
     * @param  array<array{work_item_id: int, realization: float, issues: ?string, solutions: ?string, action_plan: ?string}>  $items
     * @return Collection<int, PerformanceReport>
     */
    public function execute(
        Employee $reporter,
        int $periodMonth,
        int $periodYear,
        array $items,
    ): Collection {
        $reports = DB::transaction(function () use ($reporter, $periodMonth, $periodYear, $items) {
            return collect($items)->map(function (array $item) use ($reporter, $periodMonth, $periodYear) {
                $workItem = WorkItem::findOrFail($item['work_item_id']);

                return $this->saveReport->execute(
                    workItem: $workItem,
                    periodMonth: $periodMonth,
                    periodYear: $periodYear,
                    realization: (float) $item['realization'],
                    reporter: $reporter,
                    issues: $item['issues'] ?? null,
                    solutions: $item['solutions'] ?? null,
                    actionPlan: $item['action_plan'] ?? null,
                );
            });
        });

        PerformanceBatchSubmitted::dispatch(
            reporter: $reporter,
            periodMonth: $periodMonth,
            periodYear: $periodYear,
            reportIds: $reports->pluck('id')->all(),
            workItemIds: $reports->pluck('work_item_id')->all(),
        );

        return $reports;
    }
}
