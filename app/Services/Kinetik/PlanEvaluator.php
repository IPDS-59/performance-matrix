<?php

namespace App\Services\Kinetik;

use App\Models\KipActivity;
use App\Models\PlanItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Friday evaluation: what became of a plan item. A plan matches the member's
 * kipApp kegiatan of the same RK that overlap its dates. The PJ can override
 * the result with a reason.
 */
class PlanEvaluator
{
    public const STATES = ['done', 'in_progress', 'not_started'];

    /**
     * @param  Collection<int, KipActivity>  $activities  the plan owner's kegiatan
     * @return array{state: string, progress: float|null, activity_count: int, overridden: bool, override_reason: string|null, computed_state: string}
     */
    public function evaluate(PlanItem $plan, Collection $activities): array
    {
        $matched = $activities->filter(fn (KipActivity $a) => $this->sameRk($plan, $a) && $this->overlaps($plan, $a));
        $progress = $matched->isEmpty() ? null : round((float) $matched->avg('progress'), 1);

        $computed = match (true) {
            $matched->isEmpty() => 'not_started',
            $matched->every(fn (KipActivity $a) => (float) $a->progress >= 100) => 'done',
            default => 'in_progress',
        };

        $overridden = in_array($plan->override_status, self::STATES, true);

        return [
            'state' => $overridden ? $plan->override_status : $computed,
            'progress' => $progress,
            'activity_count' => $matched->count(),
            'overridden' => $overridden,
            'override_reason' => $overridden ? $plan->override_reason : null,
            'computed_state' => $computed,
        ];
    }

    private function sameRk(PlanItem $plan, KipActivity $activity): bool
    {
        $rk = $plan->performancePlan;
        if ($rk === null) {
            return false;
        }

        return ($rk->kip_external_id !== null && (string) $activity->rk_external_id === (string) $rk->kip_external_id)
            || $this->norm($activity->rk_name) === $this->norm($rk->description);
    }

    private function overlaps(PlanItem $plan, KipActivity $activity): bool
    {
        $start = Carbon::parse($activity->activity_date_start)->startOfDay();
        $end = Carbon::parse($activity->activity_date_end ?? $activity->activity_date_start)->endOfDay();

        return $start->lte(Carbon::parse($plan->date_end)->endOfDay()) && $end->gte(Carbon::parse($plan->date_start)->startOfDay());
    }

    private function norm(?string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $text)));
    }
}
