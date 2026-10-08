<?php

namespace App\Actions\Kinetik;

use App\Models\KipActivity;
use App\Models\PlanItem;

/**
 * After a kipApp sync, plan items follow the kipApp progres of their kegiatan.
 * kipApp wins when it changed after Kinetik wrote (Q9a).
 */
class ReconcilePlanItemsAction
{
    /**
     * @param  iterable<int>  $employeeIds
     * @return int plan items updated
     */
    public function execute(iterable $employeeIds): int
    {
        $updated = 0;

        PlanItem::whereIn('employee_id', collect($employeeIds)->all())
            ->whereNotNull('kip_external_id')
            ->whereIn('status', ['pushed', 'in_progress', 'done'])
            ->get()
            ->each(function (PlanItem $plan) use (&$updated) {
                $activity = KipActivity::where('external_id', $plan->kip_external_id)->first();
                if ($activity === null) {
                    return;
                }

                $progress = (float) $activity->progress;
                $status = match (true) {
                    $progress >= 100 => 'done',
                    $progress > 0 => 'in_progress',
                    default => 'pushed',
                };

                $plan->update(['kip_activity_id' => $activity->id, 'status' => $status, 'kip_synced_at' => now()]);
                $updated++;
            });

        return $updated;
    }
}
