<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Contracts\KipStructureSource;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Cascade enrichment: pull each employee's RK list (skp/rk) with targets parsed
 * from IKI text (skp/iki), and upsert performance_plans keyed on the kipApp rkid.
 * Complements BackfillRkAction (which creates claimable RKs from activities) by
 * setting the authoritative team and numeric target/unit.
 *
 * @phpstan-type PlanCounts array{created:int, enriched:int}
 */
class SyncKipPlansAction
{
    /**
     * @param  Collection<int, Employee>  $employees
     * @return PlanCounts
     */
    public function execute(KipStructureSource $source, Collection $employees): array
    {
        $created = 0;
        $enriched = 0;

        foreach ($employees as $employee) {
            if (empty($employee->nip_lama)) {
                continue;
            }

            foreach ($source->fetchEmployeePlans($employee->nip_lama) as $rk) {
                if ($rk->externalId === '') {
                    continue;
                }

                $plan = PerformancePlan::where('kip_external_id', $rk->externalId)->first();
                $isNew = $plan === null;
                $plan ??= new PerformancePlan(['period_type' => 'year']);

                $plan->kip_external_id = $rk->externalId;
                $plan->description = $rk->name !== '' ? $rk->name : ($plan->description ?: "RK {$rk->externalId}");

                $teamId = $rk->teamExternalId
                    ? Team::where('kip_external_id', $rk->teamExternalId)->value('id')
                    : null;
                $plan->team_id = $teamId ?? $plan->team_id ?? $employee->team_id;

                if ($rk->target !== null) {
                    $plan->target = $rk->target;
                }
                if ($rk->targetUnit !== null) {
                    $plan->target_unit = $rk->targetUnit;
                }
                $plan->pic_employee_id ??= $employee->id;

                $plan->save();

                $isNew ? $created++ : $enriched++;
            }
        }

        return ['created' => $created, 'enriched' => $enriched];
    }
}
