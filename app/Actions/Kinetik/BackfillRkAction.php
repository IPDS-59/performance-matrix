<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PerformancePlan;

/**
 * Backfill RK (performance_plans) from an employee's synced activities so every
 * kipApp activity has a claimable RK. kipApp RKs are team + employee scoped, so
 * the plan is keyed on the activity's rkid (rk_external_id) and attached to the
 * employee's home team (the cascade enrichment can later set targets).
 *
 * Existing fields (team_id, target, ...) are preserved when already set, so a
 * later cascade run is not clobbered by a backfill.
 */
class BackfillRkAction
{
    public function execute(Employee $employee): int
    {
        $rks = KipActivity::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('rk_external_id')
            ->where('rk_external_id', '!=', '')
            ->select('rk_external_id', 'rk_name')
            ->distinct()
            ->get();

        $created = 0;

        foreach ($rks as $rk) {
            $rkName = $rk->rk_name ?: "RK {$rk->rk_external_id}";

            // Dedup by (team_id, description): reuse any existing plan for this
            // team with the same text (many employees share the same RK).
            $plan = ($employee->team_id && $rk->rk_name
                ? PerformancePlan::where('team_id', $employee->team_id)
                    ->where('description', $rk->rk_name)
                    ->first()
                : null)
                ?? PerformancePlan::where('kip_external_id', $rk->rk_external_id)->first();

            $isNew = $plan === null;
            if ($isNew) {
                $plan = new PerformancePlan(['period_type' => 'year']);
                $plan->kip_external_id = $rk->rk_external_id;
            }

            $plan->description = $rkName;
            $plan->team_id ??= $employee->team_id;
            $plan->pic_employee_id ??= $employee->id;
            $plan->save();

            if ($isNew) {
                $created++;
            }
        }

        return $created;
    }
}
