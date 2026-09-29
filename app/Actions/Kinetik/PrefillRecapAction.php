<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\RecapOverride;
use App\Models\Team;
use App\Services\Kinetik\RecapAggregator;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Fill a team's monthly or quarterly recap text from the lower periods, per
 * Projek + RK row: a month from its weekly paraphrases, a quarter from its
 * monthly paraphrases (weekly ones when a row has no monthly text).
 *
 * Only empty fields are filled. Text the PJ already wrote is never changed.
 */
class PrefillRecapAction
{
    // ponytail: uraian is left out until the recap pages can edit a row's uraian (row merge, step 3).
    private const FIELDS = ['obstacle', 'solution', 'follow_up_plan'];

    /**
     * @return int number of rows that received text
     */
    public function execute(Team $team, Employee $author, string $type, int $year, ?int $month = null, ?int $quarter = null): int
    {
        $sources = $type === 'month'
            ? $this->weeklyBetween($team, $year, Carbon::create($year, $month, 1), Carbon::create($year, $month, 1)->endOfMonth())
            : $this->quarterSources($team, $year, $quarter);

        $period = [
            'team_id' => $team->id,
            'period_type' => $type,
            'period_year' => $year,
            'period_month' => $type === 'month' ? $month : null,
            'period_quarter' => $type === 'quarter' ? $quarter : null,
            'week_start' => null,
        ];

        $filled = 0;

        foreach ($sources as $source) {
            $override = RecapOverride::firstOrNew([
                ...$period,
                'performance_plan_id' => $source['performance_plan_id'],
                'project_id' => $source['project_id'],
            ]);

            foreach (self::FIELDS as $field) {
                if (blank($override->{$field}) && filled($source[$field])) {
                    $override->{$field} = $source[$field];
                }
            }

            if ($override->isDirty(self::FIELDS)) {
                $override->created_by ??= $author->id;
                $override->save();
                $filled++;
            }
        }

        return $filled;
    }

    /**
     * Monthly text first; weekly text only for rows (or fields) the months left empty.
     *
     * @return Collection<string, array<string, mixed>>
     */
    private function quarterSources(Team $team, int $year, int $quarter): Collection
    {
        $firstMonth = Carbon::create($year, ($quarter - 1) * 3 + 1, 1);

        $monthly = $this->combine(RecapOverride::query()
            ->where('team_id', $team->id)
            ->where('period_type', 'month')
            ->where('period_year', $year)
            ->whereBetween('period_month', [$firstMonth->month, $firstMonth->month + 2])
            ->orderBy('period_month')
            ->get());

        $weekly = $this->weeklyBetween($team, $year, $firstMonth, $firstMonth->copy()->addMonths(2)->endOfMonth());

        return $weekly->merge($monthly->map(fn (array $row, string $key) => [
            ...$row,
            ...collect(self::FIELDS)->mapWithKeys(fn (string $f) => [$f => $row[$f] ?? $weekly->get($key)[$f] ?? null])->all(),
        ]));
    }

    /**
     * Weekly paraphrases whose week starts in the range, as the aggregator's
     * inherited text does.
     *
     * @return Collection<string, array<string, mixed>>
     */
    private function weeklyBetween(Team $team, int $year, Carbon $start, Carbon $end): Collection
    {
        return $this->combine(RecapOverride::query()
            ->where('team_id', $team->id)
            ->where('period_type', 'week')
            ->where('period_year', $year)
            ->whereDate('week_start', '>=', $start->toDateString())
            ->whereDate('week_start', '<=', $end->toDateString())
            ->orderBy('week_start')
            ->get());
    }

    /**
     * One entry per Projek + RK row, each field the distinct texts in order.
     *
     * @param  Collection<int, RecapOverride>  $overrides
     * @return Collection<string, array<string, mixed>>
     */
    private function combine(Collection $overrides): Collection
    {
        return $overrides
            ->groupBy(fn (RecapOverride $o) => RecapAggregator::rowKey($o->performance_plan_id, $o->project_id))
            ->map(fn (Collection $rows) => [
                'performance_plan_id' => $rows->first()->performance_plan_id,
                'project_id' => $rows->first()->project_id,
                ...collect(self::FIELDS)->mapWithKeys(fn (string $f) => [$f => $this->join($rows->pluck($f))])->all(),
            ]);
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
