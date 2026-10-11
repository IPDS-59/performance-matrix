<?php

namespace App\Services\Kinetik;

use App\Models\ActivityClaim;
use App\Models\KipActivity;
use App\Models\Project;
use App\Models\RecapOverride;
use App\Models\Team;
use App\Models\WeeklyRecapRow;
use App\Models\WeeklyRecapRowClaim;
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
     * The weekly recap as the PJ edits it: one output row per kegiatan, or one
     * for several kegiatan the PJ merged. A row without a saved PJ text shows
     * what the members wrote, so the PJ does not type it again.
     *
     * @return array<int, array<string, mixed>>
     */
    public function weeklyRows(Team $team, string $weekStart): array
    {
        $claims = $this->claimsQuery($team)
            ->whereDate('week_start', $weekStart)
            ->orderBy('activity_date_start')
            ->orderBy('id')
            ->get();

        $rowOf = WeeklyRecapRowClaim::whereIn('activity_claim_id', $claims->pluck('id'))->pluck('weekly_recap_row_id', 'activity_claim_id');
        $rows = WeeklyRecapRow::whereIn('id', $rowOf->unique()->values())->get()->keyBy('id');
        $dash = fn (?string $v) => ($v === null || trim($v) === '-') ? null : $v;

        return $claims
            ->groupBy(fn (ActivityClaim $c) => $this->projectOf($c)?->id ?? 0)
            ->map(function (Collection $projectClaims) use ($rowOf, $rows, $dash) {
                $project = $this->projectOf($projectClaims->first());

                $outputRows = $projectClaims
                    ->groupBy(fn (ActivityClaim $c) => $rowOf->has($c->id) ? 'r'.$rowOf[$c->id] : 'c'.$c->id)
                    ->map(function (Collection $group, string $key) use ($rows, $dash) {
                        $row = str_starts_with($key, 'r') ? $rows->get((int) substr($key, 1)) : null;
                        $lines = $group->map(fn (ActivityClaim $c) => [
                            'claim_id' => $c->id,
                            'name' => $c->employee?->display_name ?? $c->employee?->name ?? 'Anggota',
                            'uraian' => trim((string) ($c->kipActivity?->description ?? '')) ?: null,
                            'rk_description' => $c->performancePlan?->description ?? '—',
                            'target' => $c->target !== null ? (float) $c->target : null,
                            'realization' => $c->realization !== null ? (float) $c->realization : null,
                            'target_unit' => $c->target_unit,
                            'achievement' => $c->achievement !== null ? (float) $c->achievement : null,
                            'adjusted_by' => $c->adjustedBy?->display_name ?? $c->adjustedBy?->name,
                            'pic_employee_id' => $c->performancePlan?->pic_employee_id,
                            'plan_id' => $c->performance_plan_id,
                        ])->values()->all();

                        return [
                            'key' => $key,
                            'row_id' => $row?->id,
                            'claim_ids' => $group->pluck('id')->all(),
                            'claims' => $lines,
                            'merged' => $group->count() > 1,
                            'saved' => $row?->saved_at !== null,
                            'pj_uraian' => $row?->pj_uraian,
                            'obstacle' => $row ? $row->obstacle : $this->joinText($group->pluck('obstacle')->map($dash)),
                            'solution' => $row ? $row->solution : $this->joinText($group->pluck('solution')->map($dash)),
                            'follow_up_plan' => $row ? $row->follow_up_plan : $this->joinText($group->pluck('follow_up_plan')->map($dash)),
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'project_id' => $project?->id,
                    'project_name' => $project?->name ?? $projectClaims->first()->performancePlan?->team?->name ?? '—',
                    'leader_rk' => $project?->leader_rk,
                    'rows' => $outputRows,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Per-member input status for a week, like the member blocks in the old
     * "Kegiatan Mingguan Anggota" sheet: how many kipApp activities each team
     * member has that week and how many they already saved as claims.
     *
     * @return array<int, array{employee_id: int, name: string, total: int, saved: int, status: string}>
     */
    public function memberCompleteness(Team $team, string $weekStart): array
    {
        $members = $team->members()->orderBy('employees.name')->get();
        $ids = $members->pluck('id');
        $weekEnd = Carbon::parse($weekStart)->endOfWeek(Carbon::SUNDAY)->toDateString();

        // Same rule as the member's weekly page, so a multi-week activity
        // claimed this week also counts as an activity of this week.
        $totals = KipActivity::whereIn('employee_id', $ids)
            ->duringWeek($weekStart, $weekEnd)
            ->selectRaw('employee_id, COUNT(*) as n')
            ->groupBy('employee_id')
            ->pluck('n', 'employee_id');

        $saved = ActivityClaim::whereIn('employee_id', $ids)
            ->where('status', 'saved')
            ->whereDate('week_start', $weekStart)
            ->selectRaw('employee_id, COUNT(*) as n')
            ->groupBy('employee_id')
            ->pluck('n', 'employee_id');

        return $members->map(function ($member) use ($totals, $saved) {
            $total = (int) ($totals[$member->id] ?? 0);
            $done = (int) ($saved[$member->id] ?? 0);

            return [
                'employee_id' => $member->id,
                'name' => $member->display_name ?? $member->name,
                'total' => $total,
                'saved' => $done,
                'status' => match (true) {
                    $total === 0 && $done === 0 => 'no_activity',
                    $done >= $total => 'complete',
                    $done === 0 => 'empty',
                    default => 'partial',
                },
            ];
        })->values()->all();
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
            ->with(['performancePlan.project', 'performancePlan.team', 'project', 'employee', 'kipActivity', 'adjustedBy']);
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
     * Override rows for the period, keyed by rowKey(plan, project).
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
            ->keyBy(fn (RecapOverride $o) => self::rowKey($o->performance_plan_id, $o->project_id));
    }

    /**
     * Identity of one recap row: an RK within a Projek (project may be null).
     */
    public static function rowKey(int $planId, ?int $projectId): string
    {
        return $planId.':'.($projectId ?? '');
    }

    /**
     * The Projek a claim counts toward: the member's choice, else the RK's own.
     */
    private function projectOf(ActivityClaim $claim): ?Project
    {
        return $claim->project ?? $claim->performancePlan?->project;
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
            ->groupBy(fn (ActivityClaim $c) => $this->projectOf($c)?->id ?? 0)
            ->map(function (Collection $projectClaims) use ($overrides, $withFollowUp, $inherited) {
                $project = $this->projectOf($projectClaims->first());

                $rows = $this->groupMerged($projectClaims
                    ->groupBy('performance_plan_id')
                    ->map(fn (Collection $rk) => $this->aggregateRk($rk, $project?->id, $overrides, $withFollowUp, $inherited))
                    ->values());

                $teamName = $projectClaims->first()->performancePlan?->team?->name;

                return [
                    'project_id' => $project?->id,
                    'project_name' => $project?->name ?? $teamName ?? '—',
                    // The ketua tim's RK the Projek hangs under (kipApp proyek.rencanakinerjaketua).
                    'leader_rk' => $project?->leader_rk,
                    'rows' => $rows,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Put merged rows next to each other, lead row first, at the position of
     * the group's first row.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function groupMerged(Collection $rows): array
    {
        $first = [];
        foreach ($rows as $i => $row) {
            $first[$row['merge_key'] ?? $row['row_key']] ??= $i;
        }

        return $rows
            ->map(fn (array $row, int $i) => [$row, $i])
            ->sortBy([
                fn (array $a, array $b) => $first[$a[0]['merge_key'] ?? $a[0]['row_key']] <=> $first[$b[0]['merge_key'] ?? $b[0]['row_key']],
                fn (array $a, array $b) => ($a[0]['row_key'] !== ($a[0]['merge_key'] ?? $a[0]['row_key'])) <=> ($b[0]['row_key'] !== ($b[0]['merge_key'] ?? $b[0]['row_key'])),
                fn (array $a, array $b) => $a[1] <=> $b[1],
            ])
            ->map(fn (array $pair) => $pair[0])
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
    private function aggregateRk(Collection $claims, ?int $projectId, Collection $overrides, bool $withFollowUp, Collection $inherited): array
    {
        $first = $claims->first();
        $plan = $first->performancePlan;

        $target = (float) $claims->sum('target');
        $realization = (float) $claims->sum('realization');
        $achievement = $target > 0 ? round($realization / $target * 100, 2) : null;

        $uraianAgg = $this->joinLines(
            $claims->map(fn (ActivityClaim $c) => $c->kipActivity?->description)
        );

        $uraianItems = $claims
            ->filter(fn (ActivityClaim $c) => filled($c->kipActivity?->description))
            ->map(fn (ActivityClaim $c) => [
                'name' => $c->employee?->display_name ?? $c->employee?->name ?? 'Anggota',
                'uraian' => trim((string) $c->kipActivity->description),
            ])
            ->values()
            ->all();

        // Each member claim behind the row, for the member lines and PJ corrections.
        $claimLines = $claims
            ->map(fn (ActivityClaim $c) => [
                'claim_id' => $c->id,
                'name' => $c->employee?->display_name ?? $c->employee?->name ?? 'Anggota',
                'uraian' => trim((string) ($c->kipActivity?->description ?? '')) ?: null,
                'target' => $c->target !== null ? (float) $c->target : null,
                'realization' => $c->realization !== null ? (float) $c->realization : null,
                'target_unit' => $c->target_unit,
                'achievement' => $c->achievement !== null ? (float) $c->achievement : null,
                'adjusted_by' => $c->adjustedBy?->display_name ?? $c->adjustedBy?->name,
            ])
            ->values()
            ->all();

        $obstacleAgg = $this->joinText($claims->pluck('obstacle'));
        $solutionAgg = $this->joinText($claims->pluck('solution'));
        $followUpAgg = $this->joinText($claims->pluck('follow_up_plan'));

        $key = self::rowKey($first->performance_plan_id, $projectId);
        // Paraphrases saved before claims carried a Projek have no project_id.
        $legacyKey = self::rowKey($first->performance_plan_id, null);
        $override = $overrides->get($key) ?? $overrides->get($legacyKey);
        // A merged row shows the text of its group's lead row.
        $text = $override?->merge_key ? ($overrides->get($override->merge_key) ?? $override) : $override;
        $inheritedRow = $inherited->get($key) ?? $inherited->get($legacyKey);

        $contributors = $claims
            ->map(fn (ActivityClaim $c) => $c->employee?->display_name ?? $c->employee?->name)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $row = [
            'row_key' => $key,
            'performance_plan_id' => $first->performance_plan_id,
            'project_id' => $projectId,
            'merge_key' => $override?->merge_key,
            'uraian_aggregated' => $uraianAgg,
            'uraian_items' => $uraianItems,
            'claims' => $claimLines,
            'pic_employee_id' => $plan?->pic_employee_id,
            'rk_code' => $plan?->code,
            'rk_description' => $plan?->description ?? '—',
            'target' => $target,
            'realization' => $realization,
            'achievement' => $achievement,
            // Unit must match the summed claim values (members enter target/realisasi
            // in the claim's own unit, e.g. "Kegiatan"), not the plan's IKI unit.
            'target_unit' => $first->target_unit ?? $plan?->target_unit,
            'obstacle' => $text?->obstacle ?? $obstacleAgg,
            'solution' => $text?->solution ?? $solutionAgg,
            'follow_up_plan' => $text?->follow_up_plan ?? $followUpAgg,
            'obstacle_aggregated' => $obstacleAgg,
            'solution_aggregated' => $solutionAgg,
            'follow_up_aggregated' => $followUpAgg,
            'is_overridden' => $override !== null,
            'pj_obstacle' => $text?->obstacle,
            'pj_solution' => $text?->solution,
            'pj_follow_up_plan' => $text?->follow_up_plan,
            'inherited_obstacle' => $inheritedRow['obstacle'] ?? null,
            'inherited_solution' => $inheritedRow['solution'] ?? null,
            'inherited_follow_up_plan' => $inheritedRow['follow_up_plan'] ?? null,
            'pj_uraian' => $text?->uraian,
            'is_confirmed' => $override?->confirmed_at !== null,
            'confirmed_by' => $override?->confirmedBy?->display_name ?? $override?->confirmedBy?->name,
            'contributors' => $contributors,
        ];

        if ($withFollowUp) {
            $row['follow_up_evidence_url'] = $text?->follow_up_evidence_url;
            $row['follow_up_pic'] = $text?->followUpPic?->display_name ?? $text?->followUpPic?->name;
            $row['follow_up_pic_employee_id'] = $text?->follow_up_pic_employee_id;
            $row['follow_up_deadline'] = $text?->follow_up_deadline?->toDateString();
        }

        return $row;
    }

    /**
     * Build a map of inherited (rolled-up) weekly paraphrase text for a team
     * and date range. Returns a Collection keyed by rowKey(plan, project), each
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
            ->groupBy(fn (RecapOverride $o) => self::rowKey($o->performance_plan_id, $o->project_id))
            ->map(function (Collection $rows): array {
                return [
                    'obstacle' => $this->joinText($rows->pluck('obstacle')),
                    'solution' => $this->joinText($rows->pluck('solution')),
                    'follow_up_plan' => $this->joinText($rows->pluck('follow_up_plan')),
                ];
            });
    }

    /**
     * Join non-empty text fragments with newlines, preserving duplicates so each
     * member's activity description appears individually.
     *
     * @param  Collection<int, string|null>  $values
     */
    private function joinLines(Collection $values): ?string
    {
        $joined = $values
            ->filter(fn ($v) => filled($v))
            ->map(fn ($v) => trim((string) $v))
            ->implode("\n");

        return $joined === '' ? null : $joined;
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
