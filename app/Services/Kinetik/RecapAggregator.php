<?php

namespace App\Services\Kinetik;

use App\Models\ActivityClaim;
use App\Models\RecapOverride;
use App\Models\Team;
use Carbon\Carbon;
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

        $year = Carbon::parse($weekStart)->year;
        $overrides = $this->overrides($team, 'week', $year, weekStart: $weekStart);

        return $this->segment($claims, $overrides, withFollowUp: false, inherited: collect());
    }

    /**
     * Resolve the default week anchor for a team: the week of the latest saved
     * claim, or null when no claims exist yet.
     */
    public function defaultWeekStart(Team $team): ?string
    {
        $raw = $this->latestWeekStart($team);

        return $raw ? Carbon::parse($raw)->startOfWeek(Carbon::MONDAY)->toDateString() : null;
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

        $start = Carbon::create($year, $month, 1)->toDateString();
        $end = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
        $inherited = $this->weeklyInheritedMap($team, $year, $start, $end);

        return $this->segment($claims, $overrides, withFollowUp: false, inherited: $inherited);
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

        $firstMonth = ($quarter - 1) * 3 + 1;
        $start = Carbon::create($year, $firstMonth, 1)->toDateString();
        $end = Carbon::create($year, $firstMonth + 2, 1)->endOfMonth()->toDateString();
        $inherited = $this->weeklyInheritedMap($team, $year, $start, $end);

        return $this->segment($claims, $overrides, withFollowUp: true, inherited: $inherited);
    }

    /**
     * Base query: saved claims whose RK belongs to the team — either directly
     * (team-scoped RK with project_id = null) or via the RK's project.
     */
    private function claimsQuery(Team $team): Builder
    {
        return ActivityClaim::query()
            ->where('status', 'saved')
            ->whereHas('performancePlan', fn (Builder $q) => $q->where(function (Builder $w) use ($team) {
                $w->where('team_id', $team->id)
                    ->orWhereHas('project', fn (Builder $p) => $p->where('team_id', $team->id));
            }))
            ->with(['performancePlan.project', 'performancePlan.team', 'employee']);
    }

    /**
     * Latest week_start among saved claims for the team (null if none).
     */
    public function latestWeekStart(Team $team): ?string
    {
        return $this->claimsQuery($team)->max('week_start');
    }

    /**
     * Most recent saved claim for the team, ordered by period_year / period_month desc.
     */
    public function latestClaimPeriod(Team $team): ?ActivityClaim
    {
        return $this->claimsQuery($team)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();
    }

    /**
     * Override rows for the period, keyed by performance_plan_id.
     *
     * @return Collection<int, RecapOverride>
     */
    private function overrides(
        Team $team,
        string $periodType,
        int $year,
        ?int $month = null,
        ?int $quarter = null,
        ?string $weekStart = null,
    ): Collection {
        return RecapOverride::query()
            ->with(['followUpPic', 'confirmedBy'])
            ->where('team_id', $team->id)
            ->where('period_type', $periodType)
            ->where('period_year', $year)
            ->when($month !== null, fn (Builder $q) => $q->where('period_month', $month))
            ->when($quarter !== null, fn (Builder $q) => $q->where('period_quarter', $quarter))
            ->when($weekStart !== null, fn (Builder $q) => $q->whereDate('week_start', $weekStart))
            ->get()
            ->keyBy('performance_plan_id');
    }

    /**
     * Group claims by project, then by RK, aggregating numbers and text.
     *
     * @param  Collection<int, ActivityClaim>  $claims
     * @param  Collection<int, RecapOverride>  $overrides
     * @param  Collection<int, array{obstacle: string|null, solution: string|null, follow_up_plan: string|null}>  $inherited
     * @return array<int, array<string, mixed>>
     */
    private function segment(Collection $claims, Collection $overrides, bool $withFollowUp, Collection $inherited): array
    {
        return $claims
            ->groupBy(fn (ActivityClaim $c) => $c->performancePlan?->project?->id ?? 0)
            ->map(function (Collection $projectClaims) use ($overrides, $withFollowUp, $inherited) {
                $project = $projectClaims->first()->performancePlan?->project;

                $rows = $projectClaims
                    ->groupBy('performance_plan_id')
                    ->map(fn (Collection $rk) => $this->aggregateRk($rk, $overrides, $withFollowUp, $inherited))
                    ->values()
                    ->all();

                $teamName = $projectClaims->first()->performancePlan?->team?->name;

                return [
                    'project_id' => $project?->id,
                    'project_name' => $project?->name ?? $teamName ?? '—',
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
     * @param  Collection<int, array{obstacle: string|null, solution: string|null, follow_up_plan: string|null}>  $inherited
     * @return array<string, mixed>
     */
    private function aggregateRk(Collection $claims, Collection $overrides, bool $withFollowUp, Collection $inherited): array
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
        $inheritedRow = $inherited->get($first->performance_plan_id);

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
            // Unit must match the summed claim values (members enter target/realisasi
            // in the claim's own unit, e.g. "Kegiatan"), not the plan's IKI unit.
            'target_unit' => $first->target_unit ?? $plan?->target_unit,
            'obstacle' => $override?->obstacle ?? $obstacleAgg,
            'solution' => $override?->solution ?? $solutionAgg,
            'follow_up_plan' => $override?->follow_up_plan ?? $followUpAgg,
            'obstacle_aggregated' => $obstacleAgg,
            'solution_aggregated' => $solutionAgg,
            'follow_up_aggregated' => $followUpAgg,
            'is_overridden' => $override !== null,
            'pj_obstacle' => $override?->obstacle,
            'pj_solution' => $override?->solution,
            'pj_follow_up_plan' => $override?->follow_up_plan,
            'inherited_obstacle' => $inheritedRow['obstacle'] ?? null,
            'inherited_solution' => $inheritedRow['solution'] ?? null,
            'inherited_follow_up_plan' => $inheritedRow['follow_up_plan'] ?? null,
            'is_confirmed' => $override?->confirmed_at !== null,
            'confirmed_by' => $override?->confirmedBy?->display_name ?? $override?->confirmedBy?->name,
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
     * Build a map of inherited (rolled-up) weekly paraphrase text for a team
     * and date range. Returns a Collection keyed by performance_plan_id, each
     * value being {obstacle, solution, follow_up_plan} combined from all WEEKLY
     * RecapOverrides whose week_start falls within [$start..$end].
     *
     * @return Collection<int, array{obstacle: string|null, solution: string|null, follow_up_plan: string|null}>
     */
    private function weeklyInheritedMap(Team $team, int $year, string $start, string $end): Collection
    {
        return RecapOverride::query()
            ->where('team_id', $team->id)
            ->where('period_type', 'week')
            ->where('period_year', $year)
            ->whereDate('week_start', '>=', $start)
            ->whereDate('week_start', '<=', $end)
            ->get()
            ->groupBy('performance_plan_id')
            ->map(function (Collection $rows): array {
                return [
                    'obstacle' => $this->joinText($rows->pluck('obstacle')),
                    'solution' => $this->joinText($rows->pluck('solution')),
                    'follow_up_plan' => $this->joinText($rows->pluck('follow_up_plan')),
                ];
            });
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
