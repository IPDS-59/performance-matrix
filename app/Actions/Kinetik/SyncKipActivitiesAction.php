<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Data\KipRkData;
use App\Models\Employee;
use App\Models\EmployeeRk;
use App\Models\KipActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncKipActivitiesAction
{
    public function __construct(
        private readonly BackfillRkAction $backfillRk,
        private readonly LinkPlansToProjectsAction $links,
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
        app(ReconcilePlanItemsAction::class)->execute($employees->pluck('id'));

        return $count;
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
                            'year' => $year,
                        ],
                    );
                }
                EmployeeRk::where('employee_id', $employee->id)->where('year', $year)
                    ->whereNotIn('kip_rk_id', $yearRks->map(fn (KipRkData $rk) => $rk->externalId))
                    ->delete();
            });
    }
}
