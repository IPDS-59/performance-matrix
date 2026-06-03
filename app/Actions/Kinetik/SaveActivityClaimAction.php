<?php

namespace App\Actions\Kinetik;

use App\Models\ActivityClaim;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

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

        $dateStart = Carbon::parse($data['activity_date_start']);

        $weekStart = $dateStart->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
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
            'work_item_id' => $data['work_item_id'] ?? null,
            'target' => $target,
            'realization' => $realization,
            'achievement' => $achievement,
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
            $claim = ActivityClaim::updateOrCreate(
                ['kip_activity_id' => $kipActivityId],
                $payload,
            );

            KipActivity::where('id', $kipActivityId)->update([
                'is_claimed' => $status === 'saved',
            ]);
        } else {
            $claim = ActivityClaim::create($payload);
        }

        return $claim;
    }

    /**
     * Ensure the employee belongs to (or leads) the team that owns the plan's project.
     *
     * @throws AuthorizationException
     */
    private function authorize(Employee $employee, PerformancePlan $plan): void
    {
        $project = $plan->project;

        if ($project === null) {
            throw new AuthorizationException('Rencana Kinerja tidak memiliki proyek yang valid.');
        }

        $teamId = $project->team_id;

        $isMember = $employee->teams()
            ->where('teams.id', $teamId)
            ->exists();

        if (! $isMember) {
            throw new AuthorizationException('Anda tidak memiliki akses ke Rencana Kinerja ini.');
        }
    }
}
