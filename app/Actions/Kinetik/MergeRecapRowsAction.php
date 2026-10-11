<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\RecapOverride;
use App\Services\Kinetik\RecapAggregator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Gabungkan" and "Pisahkan" for recap rows of one Projek in one period.
 *
 * Merged rows share a merge_key: the row key of the lead (first) row. The
 * group shows the lead's uraian, Permasalahan, Solusi and RTL. Each RK keeps
 * its own numbers and confirmation, and the other rows keep their own text,
 * so Pisahkan restores them unchanged.
 */
class MergeRecapRowsAction
{
    private const TEXT = ['uraian', 'obstacle', 'solution', 'follow_up_plan'];

    /**
     * @param  array<string, mixed>  $period  team_id, period_type, period_year, period_month, period_quarter, week_start
     * @param  array<int, int>  $planIds  in display order; the first becomes the lead
     */
    public function merge(array $period, ?int $projectId, array $planIds, Employee $author): string
    {
        $mergeKey = RecapAggregator::rowKey($planIds[0], $projectId);

        DB::transaction(function () use ($period, $projectId, $planIds, $author, $mergeKey) {
            $rows = collect($planIds)->map(fn (int $planId) => $this->overrideFor($period, $planId, $projectId, $author));
            $lead = $rows->first();

            // Text of the other rows fills the lead's empty fields, so nothing
            // the PJ wrote disappears from the merged row.
            foreach (self::TEXT as $field) {
                if (blank($lead->{$field})) {
                    $lead->{$field} = $this->join($rows->skip(1)->pluck($field));
                }
            }

            $rows->each(function (RecapOverride $row) use ($mergeKey) {
                $row->merge_key = $mergeKey;
                $row->save();
            });
        });

        return $mergeKey;
    }

    /**
     * @param  array<string, mixed>  $period
     */
    public function split(array $period, string $mergeKey): int
    {
        return $this->periodQuery($period)->where('merge_key', $mergeKey)->update(['merge_key' => null]);
    }

    /**
     * The row's override, creating it when missing. A paraphrase saved before
     * claims carried a Projek (project_id null) is moved to the Projek, as the
     * aggregator already shows it there.
     *
     * @param  array<string, mixed>  $period
     */
    private function overrideFor(array $period, int $planId, ?int $projectId, Employee $author): RecapOverride
    {
        $query = fn () => $this->periodQuery($period)->where('performance_plan_id', $planId);

        $override = $query()->where('project_id', $projectId)->first()
            ?? ($projectId !== null ? $query()->whereNull('project_id')->first() : null)
            ?? new RecapOverride([...$period, 'performance_plan_id' => $planId, 'created_by' => $author->id]);

        $override->project_id = $projectId;

        return $override;
    }

    /**
     * @param  array<string, mixed>  $period
     */
    private function periodQuery(array $period)
    {
        return RecapOverride::query()
            ->where('team_id', $period['team_id'])
            ->where('period_type', $period['period_type'])
            ->where('period_year', $period['period_year'])
            ->when($period['period_type'] === 'week', fn ($q) => $q->whereDate('week_start', $period['week_start']))
            ->when($period['period_type'] === 'month', fn ($q) => $q->where('period_month', $period['period_month']))
            ->when($period['period_type'] === 'quarter', fn ($q) => $q->where('period_quarter', $period['period_quarter']));
    }

    /**
     * @param  Collection<int, string|null>  $values
     */
    private function join(Collection $values): ?string
    {
        $joined = $values->filter(fn ($v) => filled($v))->map(fn ($v) => trim((string) $v))->unique()->implode("\n");

        return $joined === '' ? null : $joined;
    }
}
