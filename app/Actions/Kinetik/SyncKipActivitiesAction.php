<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Contracts\KipActivitySource;
use App\Models\Employee;
use App\Models\KipActivity;
use Illuminate\Support\Collection;

class SyncKipActivitiesAction
{
    /**
     * Fetch and upsert unsent kipApp activities for a collection of employees.
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

            $activities = $source->fetchUnsentActivities($employee->nip_lama);

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
        }

        return $count;
    }
}
