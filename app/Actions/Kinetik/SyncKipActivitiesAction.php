<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Contracts\ReportsCompleteness;
use App\Kinetik\Data\KipActivityData;
use App\Kinetik\Data\KipRkData;
use App\Models\Employee;
use App\Models\EmployeeRk;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncKipActivitiesAction
{
    public function __construct(
        private readonly BackfillRkAction $backfillRk,
        private readonly LinkPlansToProjectsAction $links,
        private readonly LabelExternalProjectsAction $labels,
    ) {}

    /**
     * Fetch and upsert kipApp activities (submitted + unsent) for a collection of employees,
     * then backfill RK (performance_plans) from those activities so they are
     * claimable.
     *
     * Employees without a nip_lama are silently skipped.
     * The operation is idempotent: re-running will update existing rows but not
     * create duplicates (keyed on external_id).
     *
     * @param  Collection<int, Employee>  $employees
     * @return int Number of activity rows upserted
     */
    public function execute(KipActivitySource $source, Collection $employees): int
    {
        $count = 0;

        foreach ($employees as $employee) {
            if (empty($employee->nip_lama)) {
                continue;
            }

            $activities = $source->fetchActivities($employee->nip_lama, $employee->kip_pegawai_id);

            foreach ($activities as $dto) {
                KipActivity::updateOrCreate(
                    ['external_id' => $dto->externalId],
                    [
                        'employee_id' => $employee->id,
                        'nip_lama' => $employee->nip_lama,
                        'description' => $dto->description,
                        'activity_date_start' => $dto->dateStart,
                        'activity_date_end' => $dto->dateEnd,
                        'time_start' => $dto->timeStart,
                        'time_end' => $dto->timeEnd,
                        'evidence_url' => $dto->evidenceUrl,
                        'rk_external_id' => $dto->rkExternalId,
                        'rk_name' => $dto->rkName,
                        'progress' => $dto->progress,
                        'achievement_note' => $dto->achievementNote,
                        'period_id' => $dto->periodId,
                        'source_year' => $dto->sourceYear,
                        'sent_at' => $dto->sentAt,
                        'raw_payload' => $dto->raw,
                        'fetched_at' => now(),
                    ],
                );

                $count++;
            }

            if ($source instanceof ReportsCompleteness && $source->lastFetchWasComplete()) {
                $this->removeDeleted($employee, $activities);
            }

            $this->backfillRk->execute($employee);

            // Leader RK of each RK, to link RK to their Projek. Two kipApp
            // calls; a failure here must not stop the activity sync.
            if ($employee->kip_pegawai_id) {
                try {
                    $rks = $source->fetchYearlyRks((string) $employee->kip_pegawai_id);
                    $this->links->rememberLeaderRks($rks);
                    $this->rememberEmployeeRks($employee, $rks);
                } catch (Throwable $e) {
                    Log::warning('Leader RK sync failed', ['employee_id' => $employee->id, 'error' => $e->getMessage()]);
                }
            }
        }

        $this->links->execute();
        app(ImportKipActivitiesAsPlanItemsAction::class)->execute($employees->pluck('id'));
        app(ReconcilePlanItemsAction::class)->execute($employees->pluck('id'));

        return $count;
    }

    /**
     * A kegiatan deleted in kipApp stops being "Ada di kipApp" in Kinetik: its stored row goes, and its plan is cancelled
     * like one deleted from Kinetik. Only after a fetch that read every kegiatan of the period, and only that period's
     * kegiatan. A fetch that came back empty is not trusted to mean the member deleted everything.
     *
     * @param  Collection<int, KipActivityData>  $activities
     */
    private function removeDeleted(Employee $employee, Collection $activities): void
    {
        if ($activities->isEmpty()) {
            return;
        }

        $gone = KipActivity::where('employee_id', $employee->id)
            ->whereYear('activity_date_start', (int) config('kinetik.kip.tahun'))
            ->whereNotIn('external_id', $activities->pluck('externalId')->all())
            ->get(['id', 'external_id']);
        if ($gone->isEmpty()) {
            return;
        }

        PlanItem::where('employee_id', $employee->id)->whereIn('kip_external_id', $gone->pluck('external_id'))
            ->where('status', '!=', 'cancelled')->update(['status' => 'cancelled']);
        KipActivity::whereKey($gone->pluck('id'))->delete();
        Log::info('Kegiatan deleted in kipApp removed', ['employee_id' => $employee->id, 'external_ids' => $gone->pluck('external_id')->all()]);
    }

    /**
     * Keep the member's own RK list (for "RK belum ada kegiatan" on the plan
     * board): the RK kipApp returned replace the stored ones of that year.
     *
     * @param  Collection<int, KipRkData>  $rks
     */
    private function rememberEmployeeRks(Employee $employee, Collection $rks): void
    {
        $rks->groupBy(fn (KipRkData $rk) => (int) ($rk->raw['tahun'] ?? now()->year))
            ->each(function (Collection $yearRks, int $year) use ($employee) {
                foreach ($yearRks as $rk) {
                    EmployeeRk::updateOrCreate(
                        ['employee_id' => $employee->id, 'kip_rk_id' => $rk->externalId],
                        [
                            'name' => $rk->name,
                            'leader_rk' => trim((string) ($rk->raw['rencanakinerjaatasan'] ?? '')) ?: null,
                            'team_kip_id' => $rk->teamExternalId,
                            'is_additional' => (int) ($rk->raw['jeniskinerja'] ?? 1) === 2,
                            'year' => $year,
                        ],
                    );
                }
                $this->ensureAdditionalPlans($employee, $yearRks->filter(fn (KipRkData $rk) => (int) ($rk->raw['jeniskinerja'] ?? 1) === 2));
                $this->ensureExternalPlans($employee, $yearRks->filter(fn (KipRkData $rk) => (int) ($rk->raw['jeniskinerja'] ?? 1) !== 2));
                $this->labelExternalProjects($employee, $yearRks->filter(fn (KipRkData $rk) => (int) ($rk->raw['jeniskinerja'] ?? 1) !== 2));
                EmployeeRk::where('employee_id', $employee->id)->where('year', $year)
                    ->whereNotIn('kip_rk_id', $yearRks->map(fn (KipRkData $rk) => $rk->externalId))
                    ->delete();
            });
    }

    /**
     * An RK of a team Kinetik does not hold (a team of the Pusat or another satker the member also works for) has no plan row, because
     * team sync only covers the Sulteng teams. The same goes for an RK the team sync missed. The member's own SKP does list it, and it is on their quarter SKP in kipApp, so it gets a
     * plan row owned by the member: it shows in their RK list, the projek is optional, and kegiatan under it go to kipApp by RK text.
     *
     * @param  Collection<int, KipRkData>  $rks
     */
    private function ensureExternalPlans(Employee $employee, Collection $rks): void
    {
        $norm = fn (?string $t) => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $t)));
        $ownTeamIds = $employee->teams()->pluck('teams.id');
        $visible = PerformancePlan::where(fn ($q) => $q->whereIn('team_id', $ownTeamIds)->orWhereHas('project', fn ($p) => $p->whereIn('team_id', $ownTeamIds))->orWhere('owner_employee_id', $employee->id))
            ->pluck('description')->map($norm)->flip();

        foreach ($rks as $rk) {
            if ($visible->has($norm($rk->name))) {
                continue;
            }
            PerformancePlan::create(['description' => $rk->name, 'team_id' => null, 'kip_external_id' => $rk->externalId, 'is_additional' => false, 'owner_employee_id' => $employee->id]);
            $visible->put($norm($rk->name), 0);
        }
    }

    /**
     * Names the Pusat team and projek of those RK. Extra kipApp calls, so a failure only leaves the labels as they were.
     *
     * @param  Collection<int, KipRkData>  $rks
     */
    private function labelExternalProjects(Employee $employee, Collection $rks): void
    {
        try {
            $this->labels->execute($employee, $rks);
        } catch (Throwable $e) {
            Log::warning('Pusat projek label failed', ['employee_id' => $employee->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * An RK tambahan has no team or projek in kipApp, so no sync of a team brings it in: the member's own SKP does. It gets a
     * plan row owned by the member, so it shows in their RK list and their kegiatan can be filed under it.
     *
     * @param  Collection<int, KipRkData>  $additional
     */
    private function ensureAdditionalPlans(Employee $employee, Collection $additional): void
    {
        $norm = fn (?string $t) => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $t)));
        $teamId = $employee->teams()->orderByDesc('employee_team.is_primary')->value('teams.id');

        foreach ($additional as $rk) {
            $plan = PerformancePlan::where('owner_employee_id', $employee->id)->get()->first(fn (PerformancePlan $p) => $norm($p->description) === $norm($rk->name))
                ?? PerformancePlan::whereNull('team_id')->whereNull('project_id')->where('is_additional', false)->get()->first(fn (PerformancePlan $p) => $norm($p->description) === $norm($rk->name));

            if ($plan === null) {
                PerformancePlan::create(['description' => $rk->name, 'team_id' => $teamId, 'kip_external_id' => $rk->externalId, 'is_additional' => true, 'owner_employee_id' => $employee->id]);

                continue;
            }
            $plan->forceFill(['is_additional' => true, 'owner_employee_id' => $employee->id])->save();
        }
    }
}
