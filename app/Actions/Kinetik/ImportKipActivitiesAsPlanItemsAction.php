<?php

namespace App\Actions\Kinetik;

use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use App\Services\Kinetik\KegiatanDuplicateFinder;
use Illuminate\Support\Carbon;

/**
 * Rekap Mingguan reads plan items. A kegiatan that already lives in kipApp has
 * none yet, so each synced kegiatan without a plan item gets one: it starts as
 * what kipApp says (progres, uraian, bukti, sent date) and the member adds the
 * target and capaian numbers afterwards.
 */
class ImportKipActivitiesAsPlanItemsAction
{
    public function __construct(private readonly KegiatanDuplicateFinder $finder = new KegiatanDuplicateFinder) {}

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

                    // The kegiatan may already be here from a spreadsheet import (worded a little differently, never sent):
                    // the kipApp copy joins that one instead of making a second.
                    if ($this->joinImported($activity, $plan, $progress)) {
                        continue;
                    }

                    PlanItem::create([
                        'team_id' => $teamId,
                        'employee_id' => $activity->employee_id,
                        'performance_plan_id' => $plan->id,
                        'project_id' => $this->projectFor($plan, (int) $activity->employee_id),
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

        $this->fillMissingProjects($employeeIds);

        return $created;
    }

    /**
     * A kegiatan from kipApp has no projek of its own (kipApp keeps none), so it takes the projek of its RK. A team leader's
     * RK Ketua sits above the projek rather than in one: it gives the projek only when it heads exactly one projek of theirs.
     * Under an RK Ketua of several projek kipApp cannot tell which, so the kegiatan stays without one.
     */
    private function projectFor(PerformancePlan $plan, int $employeeId): ?int
    {
        if ($plan->project_id !== null) {
            return $plan->project_id;
        }
        $norm = fn (?string $t) => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $t)));
        $headed = Project::where('leader_id', $employeeId)->whereNotNull('leader_rk')->get(['id', 'leader_rk'])
            ->filter(fn (Project $p) => $norm($p->leader_rk) === $norm($plan->description));

        return $headed->count() === 1 ? $headed->first()->id : null;
    }

    /**
     * Kegiatan from kipApp that came in before their RK had a projek (or before this rule) get it now, sent ones too:
     * it only groups them in Kinetik and changes nothing in kipApp. Kegiatan made in Kinetik keep the member's choice.
     *
     * @param  iterable<int>|null  $employeeIds
     */
    private function fillMissingProjects(?iterable $employeeIds): void
    {
        PlanItem::query()
            ->with('performancePlan:id,project_id,description')
            ->when($employeeIds !== null, fn ($q) => $q->whereIn('employee_id', collect($employeeIds)->all()))
            ->where('source', 'kipapp')
            ->whereNull('project_id')
            ->whereNotNull('performance_plan_id')
            ->chunkById(500, function ($items) {
                foreach ($items as $item) {
                    $projectId = $item->performancePlan ? $this->projectFor($item->performancePlan, (int) $item->employee_id) : null;
                    if ($projectId !== null) {
                        $item->update(['project_id' => $projectId]);
                    }
                }
            });
    }

    /** @return bool true when an imported kegiatan of the same person, day and words took the kipApp id */
    private function joinImported(KipActivity $activity, PerformancePlan $plan, int $progress): bool
    {
        $day = Carbon::parse($activity->activity_date_start)->toDateString();
        $match = PlanItem::where('employee_id', $activity->employee_id)->where('source', 'import')->where('status', 'planned')
            ->whereNull('kip_external_id')->whereDate('date_start', $day)->get()
            ->first(fn (PlanItem $i) => $this->finder->similarity((string) $i->description, (string) $activity->description) >= KegiatanDuplicateFinder::SAME);
        if ($match === null) {
            return false;
        }

        $match->update([
            'kip_external_id' => $activity->external_id,
            'kip_activity_id' => $activity->id,
            'kip_sent_at' => $activity->sent_at,
            'kip_synced_at' => now(),
            'status' => match (true) {
                $progress >= 100 => 'done',
                $progress > 0 => 'in_progress',
                default => 'pushed',
            },
            'progress' => max($progress, (int) $match->progress),
            'evidence_url' => $match->evidence_url ?: $activity->evidence_url,
            'capaian_note' => $match->capaian_note ?: $activity->achievement_note,
        ]);

        return true;
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
