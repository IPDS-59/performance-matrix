<?php

namespace App\Listeners;

use App\Events\PerformanceBatchSubmitted;
use App\Events\PerformanceReportSaved;
use Illuminate\Support\Facades\Log;

class LogPerformanceActivity
{
    public function handle(PerformanceReportSaved|PerformanceBatchSubmitted $event): void
    {
        if ($event instanceof PerformanceBatchSubmitted) {
            Log::info('Performance batch submitted', [
                'reporter_id' => $event->reporter->id,
                'period' => "{$event->periodYear}-{$event->periodMonth}",
                'report_count' => count($event->reportIds),
            ]);

            return;
        }

        Log::info('Performance report saved', [
            'report_id' => $event->report->id,
            'work_item_id' => $event->report->work_item_id,
            'period' => "{$event->report->period_year}-{$event->report->period_month}",
            'achievement' => $event->report->achievement_percentage,
        ]);
    }
}
