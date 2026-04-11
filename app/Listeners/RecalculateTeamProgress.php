<?php

namespace App\Listeners;

use App\Events\PerformanceBatchSubmitted;
use App\Events\PerformanceReportSaved;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecalculateTeamProgress
{
    public function handle(PerformanceReportSaved|PerformanceBatchSubmitted $event): void
    {
        if ($event instanceof PerformanceBatchSubmitted) {
            $this->recalculate($event->periodMonth, $event->periodYear);

            return;
        }

        $this->recalculate(
            $event->report->period_month,
            $event->report->period_year
        );
    }

    private function recalculate(int $month, int $year): void
    {
        $cacheKey = "team_progress:{$year}:{$month}";

        $progress = DB::table('performance_reports')
            ->join('work_items', 'work_items.id', '=', 'performance_reports.work_item_id')
            ->join('projects', 'projects.id', '=', 'work_items.project_id')
            ->where('performance_reports.period_month', $month)
            ->where('performance_reports.period_year', $year)
            ->groupBy('projects.team_id')
            ->select(
                'projects.team_id',
                DB::raw('AVG(performance_reports.achievement_percentage) as avg_achievement'),
                DB::raw('COUNT(performance_reports.id) as report_count')
            )
            ->get()
            ->keyBy('team_id');

        Cache::put($cacheKey, $progress, now()->addHours(6));
    }
}
