<?php

namespace App\Actions\Kinetik;

use App\Models\PlanItem;
use Carbon\Carbon;

/**
 * Pushes every finished plan (progres 100, capaian, bukti dukung) whose start date has come, also one entered after its dates.
 * A plan that is not finished stays in Kinetik until it is.
 */
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
            // Imported from Excel: the list is only made in Kinetik. The member sends it from the kegiatan's detail.
            ->where('source', '!=', 'import')
            ->whereDate('date_start', '<=', $today)
            // Finished plans go whenever they were entered; with the finished-only rule off, only plans whose dates include today.
            ->when(! config('kinetik.kip.push_only_finished'), fn ($q) => $q->whereDate('date_end', '>=', $today))
            // A plan kipApp keeps refusing (SKP locked, no RK) is not tried every hour for ever.
            ->where('push_attempts', '<', 5)
            ->orderBy('date_start')
            ->get()
            ->filter(fn (PlanItem $plan) => $plan->missingForKip() === [])
            ->each(function (PlanItem $plan) use (&$result) {
                $this->push->execute($plan) ? $result['pushed']++ : $result['failed']++;
            });

        return $result;
    }
}
