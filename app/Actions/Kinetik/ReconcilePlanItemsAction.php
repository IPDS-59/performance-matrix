<?php

namespace App\Actions\Kinetik;

use App\Kinetik\PlanStatus;
use App\Models\KipActivity;
use App\Models\PlanItem;

/**
 * After a kipApp sync, plan items follow the kipApp progres of their kegiatan (kipApp wins, Q9a), and an empty uraian
 * or bukti dukung in Kinetik takes what the member typed in kipApp.
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

                $plan->update([
                    'kip_activity_id' => $activity->id,
                    'status' => PlanStatus::fromKipProgress((float) $activity->progress)->value,
                    'kip_sent_at' => $activity->sent_at,
                    'kip_synced_at' => now(),
                    // What the member typed in kipApp itself comes back when Kinetik has nothing; Kinetik's own text is never replaced.
                    'evidence_url' => filled($plan->evidence_url) ? $plan->evidence_url : $activity->evidence_url,
                    'capaian_note' => filled($plan->capaian_note) ? $plan->capaian_note : $activity->achievement_note,
                ]);
                $updated++;
            });

        return $updated;
    }
}
