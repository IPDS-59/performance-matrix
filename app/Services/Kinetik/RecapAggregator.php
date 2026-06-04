<?php

namespace App\Services\Kinetik;

use App\Models\ActivityClaim;
use App\Models\RecapOverride;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Live indexed aggregation over saved activity claims, grouped by project and
 * Rencana Kinerja (RK). Portable across SQLite (local/CI) and Postgres (prod) —
 * no materialized views, only indexed WHERE/GROUP queries.
 */
class RecapAggregator
{
    /**
     * Team weekly recap — all members' saved claims for the week, segmented by
     * project. No paraphrase overrides (raw aggregation only).
     *
     * @return array<int, array<string, mixed>>
     */
    public function weekly(Team $team, string $weekStart): array
    {
        $claims = $this->claimsQuery($team)
            ->whereDate('week_start', $weekStart)
            ->get();

        return $this->segment($claims, collect(), withFollowUp: false);
    }

    /**
     * Team monthly recap — segmented by project, paraphrase overrides merged.
     *
     * @return array<int, array<string, mixed>>
     */
    public function monthly(Team $team, int $year, int $month): array
    {
        $claims = $this->claimsQuery($team)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->get();

        $overrides = $this->overrides($team, 'month', $year, month: $month);

        return $this->segment($claims, $overrides, withFollowUp: false);
    }

    /**
     * Team quarterly recap (FRA format) — paraphrase overrides plus follow-up
     * evidence / PIC / deadline merged.
     *
     * @return array<int, array<string, mixed>>
     */
    public function quarterly(Team $team, int $year, int $quarter): array
    {
        $claims = $this->claimsQuery($team)
            ->where('period_year', $year)
            ->where('period_quarter', $quarter)
            ->get();

        $overrides = $this->overrides($team, 'quarter', $year, quarter: $quarter);

        return $this->segment($claims, $overrides, withFollowUp: true);
    }

    /**
     * Base query: saved claims whose RK's project belongs to the team.
     */
    private function claimsQuery(Team $team): Builder
    {
        return ActivityClaim::query()
            ->where('status', 'saved')
            ->whereHas('performancePlan.project', fn (Builder $q) => $q->where('team_id', $team->id))
            ->with(['performancePlan.project', 'employee']);
    }

    /**
     * Override rows for the period, keyed by performance_plan_id.
     *
     * @return Collection<int, RecapOverride>
     */
    private function overrides(Team $team, string $periodType, int $year, ?int $month = null, ?int $quarter = null): Collection
    {
        return RecapOverride::query()
            ->with('followUpPic')
            ->where('team_id', $team->id)
            ->where('period_type', $periodType)
            ->where('period_year', $year)
            ->when($month !== null, fn (Builder $q) => $q->where('period_month', $month))
            ->when($quarter !== null, fn (Builder $q) => $q->where('period_quarter', $quarter))
            ->get()
            ->keyBy('performance_plan_id');
    }

    /**
     * Group claims by project, then by RK, aggregating numbers and text.
     *
     * @param  Collection<int, ActivityClaim>  $claims
     * @param  Collection<int, RecapOverride>  $overrides
     * @return array<int, array<string, mixed>>
     */
    private function segment(Collection $claims, Collection $overrides, bool $withFollowUp): array
    {
        return $claims
            ->groupBy(fn (ActivityClaim $c) => $c->performancePlan?->project?->id ?? 0)
            ->map(function (Collection $projectClaims) use ($overrides, $withFollowUp) {
                $project = $projectClaims->first()->performancePlan?->project;

                $rows = $projectClaims
                    ->groupBy('performance_plan_id')
                    ->map(fn (Collection $rk) => $this->aggregateRk($rk, $overrides, $withFollowUp))
                    ->values()
                    ->all();

                return [
                    'project_id' => $project?->id,
                    'project_name' => $project?->name ?? '—',
                    'rows' => $rows,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Aggregate one RK row across all contributing claims.
     *
     * @param  Collection<int, ActivityClaim>  $claims
     * @param  Collection<int, RecapOverride>  $overrides
     * @return array<string, mixed>
     */
    private function aggregateRk(Collection $claims, Collection $overrides, bool $withFollowUp): array
    {
        $first = $claims->first();
        $plan = $first->performancePlan;

        $target = (float) $claims->sum('target');
        $realization = (float) $claims->sum('realization');
        $achievement = $target > 0 ? round($realization / $target * 100, 2) : null;

        $obstacleAgg = $this->joinText($claims->pluck('obstacle'));
        $solutionAgg = $this->joinText($claims->pluck('solution'));
        $followUpAgg = $this->joinText($claims->pluck('follow_up_plan'));

        $override = $overrides->get($first->performance_plan_id);

        $contributors = $claims
            ->map(fn (ActivityClaim $c) => $c->employee?->display_name ?? $c->employee?->name)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $row = [
            'performance_plan_id' => $first->performance_plan_id,
            'rk_code' => $plan?->code,
            'rk_description' => $plan?->description ?? '—',
            'target' => $target,
            'realization' => $realization,
            'achievement' => $achievement,
            'target_unit' => $plan?->target_unit,
            'obstacle' => $override?->obstacle ?? $obstacleAgg,
            'solution' => $override?->solution ?? $solutionAgg,
            'follow_up_plan' => $override?->follow_up_plan ?? $followUpAgg,
            'obstacle_aggregated' => $obstacleAgg,
            'solution_aggregated' => $solutionAgg,
            'follow_up_aggregated' => $followUpAgg,
            'is_overridden' => $override !== null,
            'contributors' => $contributors,
        ];

        if ($withFollowUp) {
            $row['follow_up_evidence_url'] = $override?->follow_up_evidence_url;
            $row['follow_up_pic'] = $override?->followUpPic?->display_name ?? $override?->followUpPic?->name;
            $row['follow_up_pic_employee_id'] = $override?->follow_up_pic_employee_id;
            $row['follow_up_deadline'] = $override?->follow_up_deadline?->toDateString();
        }

        return $row;
    }

    /**
     * Join distinct non-empty text fragments with "; ".
     *
     * @param  Collection<int, string|null>  $values
     */
    private function joinText(Collection $values): ?string
    {
        $joined = $values
            ->filter(fn ($v) => filled($v))
            ->map(fn ($v) => trim((string) $v))
            ->unique()
            ->implode('; ');

        return $joined === '' ? null : $joined;
    }
}
