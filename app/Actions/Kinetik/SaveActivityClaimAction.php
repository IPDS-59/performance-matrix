<?php

namespace App\Actions\Kinetik;

use App\Models\ActivityClaim;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\RecapLock;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class SaveActivityClaimAction
{
    /**
     * Persist a weekly activity claim for the given employee.
     *
     * Authorization: the performance_plan's project must belong to a team
     * that the employee is a member (or leader) of.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function execute(Employee $employee, array $data): ActivityClaim
    {
        /** @var PerformancePlan $plan */
        $plan = PerformancePlan::with('project')->findOrFail($data['performance_plan_id']);

        $this->authorize($employee, $plan);

        $projectId = $this->resolveProjectId($plan, $data['project_id'] ?? null);

        $dateStart = Carbon::parse($data['activity_date_start']);

        $weekStart = $dateStart->copy()->startOfWeek(Carbon::MONDAY)->toDateString();

        $this->ensureUnlocked($plan, $dateStart, $data['kip_activity_id'] ?? null, $weekStart);
        $periodYear = (int) $dateStart->year;
        $periodMonth = (int) $dateStart->month;
        $periodQuarter = (int) intdiv($periodMonth - 1, 3) + 1;

        $target = isset($data['target']) ? (float) $data['target'] : null;
        $realization = isset($data['realization']) ? (float) $data['realization'] : null;

        $achievement = null;
        if ($target !== null && $target > 0 && $realization !== null) {
            $achievement = round($realization / $target * 100, 2);
        }

        $status = $data['status'] ?? 'draft';

        $payload = [
            'employee_id' => $employee->id,
            'performance_plan_id' => $plan->id,
            'project_id' => $projectId,
            'work_item_id' => $data['work_item_id'] ?? null,
            'target' => $target,
            'realization' => $realization,
            'achievement' => $achievement,
            // The member's own numbers again, no longer the PJ's correction.
            'adjusted_by' => null,
            'target_unit' => $data['target_unit'] ?? null,
            'obstacle' => $data['obstacle'] ?? null,
            'solution' => $data['solution'] ?? null,
            'follow_up_plan' => $data['follow_up_plan'] ?? null,
            'activity_date_start' => $data['activity_date_start'],
            'activity_date_end' => $data['activity_date_end'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'evidence_url' => $data['evidence_url'] ?? null,
            'status' => $status,
            'week_start' => $weekStart,
            'period_year' => $periodYear,
            'period_quarter' => $periodQuarter,
            'period_month' => $periodMonth,
            'reserved_1' => $data['reserved_1'] ?? null,
            'reserved_2' => $data['reserved_2'] ?? null,
            'reserved_3' => $data['reserved_3'] ?? null,
            'claimed_at' => $status === 'saved' ? now() : null,
        ];

        $kipActivityId = $data['kip_activity_id'] ?? null;

        if ($kipActivityId !== null) {
            /** @var ActivityClaim $claim */
            // One claim per activity and week: a multi-week activity is claimed
            // again each week it covers.
            // whereDate: week_start is stored with a time part on some drivers.
            $claim = ActivityClaim::where('kip_activity_id', $kipActivityId)->whereDate('week_start', $weekStart)->first()
                ?? new ActivityClaim(['kip_activity_id' => $kipActivityId]);
            $claim->fill($payload)->save();

            // "Diklaim" once any week of the activity is saved.
            KipActivity::where('id', $kipActivityId)->update([
                'is_claimed' => ActivityClaim::where('kip_activity_id', $kipActivityId)->where('status', 'saved')->exists(),
            ]);
        } else {
            $claim = ActivityClaim::create($payload);
        }

        return $claim;
    }

    /**
     * A PJ locks the team recap before the meeting. Claims in a locked period
     * (the new date, or the date of the claim being edited) are frozen.
     *
     * @throws ValidationException
     */
    private function ensureUnlocked(PerformancePlan $plan, Carbon $date, mixed $kipActivityId, string $weekStart): void
    {
        $teamId = $plan->project?->team_id ?? $plan->team_id;

        $existingDate = $kipActivityId !== null
            ? ActivityClaim::where('kip_activity_id', $kipActivityId)->whereDate('week_start', $weekStart)->value('activity_date_start')
            : null;

        $dates = array_filter([$date, $existingDate ? Carbon::parse($existingDate) : null]);

        foreach ($dates as $d) {
            if ($teamId !== null && RecapLock::coversDate($teamId, $d)) {
                throw ValidationException::withMessages([
                    'performance_plan_id' => 'Rekap tim untuk periode ini sudah dikunci PJ. Minta PJ membuka kunci untuk mengubah.',
                ]);
            }
        }
    }

    /**
     * The claimed Projek must belong to the RK's team. An RK tied to a project
     * always uses that project; a team-scoped RK takes the member's choice.
     */
    private function resolveProjectId(PerformancePlan $plan, mixed $projectId): ?int
    {
        if ($plan->project_id !== null) {
            return $plan->project_id;
        }

        if ($projectId === null || $projectId === '') {
            if (! app(LinkPlansToProjectsAction::class)->projectOptional($plan)) {
                throw ValidationException::withMessages(['project_id' => 'Pilih Projek kegiatan ini.']);
            }

            return null;
        }

        $valid = Project::whereKey((int) $projectId)
            ->where('team_id', $plan->team_id)
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'project_id' => 'Projek tidak termasuk dalam tim Rencana Kinerja ini.',
            ]);
        }

        return (int) $projectId;
    }

    /**
     * Ensure the employee belongs to (or leads) the plan's team. kipApp RKs are
     * team-scoped (no project), so fall back to the plan's own team_id.
     *
     * @throws AuthorizationException
     */
    private function authorize(Employee $employee, PerformancePlan $plan): void
    {
        $teamId = $plan->project?->team_id ?? $plan->team_id;

        if ($teamId === null) {
            throw new AuthorizationException('Rencana Kinerja tidak terkait dengan tim mana pun.');
        }

        $isMember = $employee->teams()
            ->where('teams.id', $teamId)
            ->exists();

        if (! $isMember) {
            throw new AuthorizationException('Anda tidak memiliki akses ke Rencana Kinerja ini.');
        }
    }
}
