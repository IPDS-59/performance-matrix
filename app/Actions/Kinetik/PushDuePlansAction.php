<?php

namespace App\Actions\Kinetik;

use App\Models\PlanItem;
use Carbon\Carbon;

/** Pushes every plan whose start date has come and whose end date has not passed (Q4, Q14). */
class PushDuePlansAction
{
    public function __construct(private readonly PushPlanItemAction $push) {}

    /** @return array{pushed: int, failed: int} */
    public function execute(?Carbon $now = null): array
    {
        $today = ($now ?? now(config('app.schedule_timezone', 'Asia/Makassar')))->toDateString();
        $result = ['pushed' => 0, 'failed' => 0];

        PlanItem::with(['employee.user', 'performancePlan'])
            ->where('status', 'planned')
            ->whereDate('date_start', '<=', $today)
            ->whereDate('date_end', '>=', $today)
            ->orderBy('date_start')
            ->get()
            ->each(function (PlanItem $plan) use (&$result) {
                $this->push->execute($plan) ? $result['pushed']++ : $result['failed']++;
            });

        return $result;
    }
}
