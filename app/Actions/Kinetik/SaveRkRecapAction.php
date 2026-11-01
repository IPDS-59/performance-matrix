<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\RkRecap;
use Carbon\Carbon;

/**
 * The PJ (or the RK's PIC) saves the paraphrase, target, satuan and capaian of
 * one RK for a week or month.
 */
class SaveRkRecapAction
{
    /**
     * @param  array{team_id: int, performance_plan_id: int, period_type: string, period_start: string, paraphrase?: ?string, target?: mixed, target_unit?: ?string, capaian?: mixed}  $data
     */
    public function save(Employee $actor, array $data): RkRecap
    {
        $key = $this->key($data);
        $recap = $this->find($key) ?? new RkRecap($key);

        $recap->fill([
            'paraphrase' => filled($data['paraphrase'] ?? null) ? trim($data['paraphrase']) : null,
            'target' => $data['target'] ?? null,
            'target_unit' => filled($data['target_unit'] ?? null) ? trim($data['target_unit']) : null,
            'capaian' => $data['capaian'] ?? null,
            'created_by' => $recap->created_by ?? $actor->id,
        ]);

        $recap->save();

        return $recap;
    }

    /** @param  array{team_id: int, performance_plan_id: int, period_type: string, period_start: string}  $key */
    private function find(array $key): ?RkRecap
    {
        return RkRecap::forPeriod($key['team_id'], $key['period_type'], $key['period_start'])
            ->where('performance_plan_id', $key['performance_plan_id'])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{team_id: int, performance_plan_id: int, period_type: string, period_start: string}
     */
    private function key(array $data): array
    {
        $start = Carbon::parse($data['period_start']);

        return [
            'team_id' => (int) $data['team_id'],
            'performance_plan_id' => (int) $data['performance_plan_id'],
            'period_type' => $data['period_type'],
            'period_start' => ($data['period_type'] === 'week' ? $start->startOfWeek(Carbon::MONDAY) : $start->startOfMonth())->toDateString(),
        ];
    }
}
