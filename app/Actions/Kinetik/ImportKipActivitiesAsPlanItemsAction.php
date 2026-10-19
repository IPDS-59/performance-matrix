<?php

namespace App\Actions\Kinetik;

use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\PlanItem;

/**
 * Rekap Mingguan reads plan items. A kegiatan that already lives in kipApp has
 * none yet, so each synced kegiatan without a plan item gets one: it starts as
 * what kipApp says (progres, uraian, bukti, sent date) and the member adds the
 * target and capaian numbers afterwards.
 */
class ImportKipActivitiesAsPlanItemsAction
{
    /**
     * @param  iterable<int>|null  $employeeIds  null = every employee
     * @return int plan items created
     */
    public function execute(?iterable $employeeIds = null): int
    {
        $created = 0;

        KipActivity::query()
            ->with('employee:id,team_id')
            ->when($employeeIds !== null, fn ($q) => $q->whereIn('employee_id', collect($employeeIds)->all()))
            ->whereNotNull('employee_id')
            ->whereNotNull('activity_date_start')
            ->whereNotIn('external_id', PlanItem::whereNotNull('kip_external_id')->select('kip_external_id'))
            ->orderBy('id')
            ->chunkById(500, function ($activities) use (&$created) {
                foreach ($activities as $activity) {
                    $plan = $this->planFor($activity);
                    $teamId = $plan?->team_id ?? $activity->employee?->team_id;
                    if ($plan === null || $teamId === null) {
                        continue;
                    }

                    $progress = (int) $activity->progress;

                    PlanItem::create([
                        'team_id' => $teamId,
                        'employee_id' => $activity->employee_id,
                        'performance_plan_id' => $plan->id,
                        'description' => $activity->description,
                        'date_start' => $activity->activity_date_start,
                        'date_end' => $activity->activity_date_end ?? $activity->activity_date_start,
                        'time_start' => $activity->time_start,
                        'time_end' => $activity->time_end,
                        'capaian_note' => $activity->achievement_note,
                        'evidence_url' => $activity->evidence_url,
                        'progress' => $progress,
                        'source' => 'kipapp',
                        'status' => match (true) {
                            $progress >= 100 => 'done',
                            $progress > 0 => 'in_progress',
                            default => 'pushed',
                        },
                        'kip_external_id' => $activity->external_id,
                        'kip_activity_id' => $activity->id,
                        'kip_sent_at' => $activity->sent_at,
                        'kip_synced_at' => now(),
                    ]);
                    $created++;
                }
            });

        return $created;
    }

    private function planFor(KipActivity $activity): ?PerformancePlan
    {
        if (blank($activity->rk_external_id)) {
            return null;
        }

        return PerformancePlan::where('kip_external_id', $activity->rk_external_id)->first()
            ?? ($activity->rk_name && $activity->employee?->team_id
                ? PerformancePlan::where('team_id', $activity->employee->team_id)->where('description', $activity->rk_name)->first()
                : null);
    }
}
