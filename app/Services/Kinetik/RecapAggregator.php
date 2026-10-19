<?php

namespace App\Services\Kinetik;

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use App\Models\RecapOverride;
use App\Models\RkRecap;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The team recap, read from the members' plan items and what the PJ wrote in
 * rk_recaps (week, month) or recap_overrides (quarter, FRA). Live queries only,
 * portable across SQLite (local/CI) and Postgres (prod).
 */
class RecapAggregator
{
    /**
     * Week and month: one panel per RK of the team, for the paraphrase box.
     * Entries are the members' own plan items; the PJ cannot edit them here.
     *
     * @return list<array<string, mixed>>
     */
    public function panels(Team $team, string $type, Carbon $start): array
    {
        [$from, $to] = $this->range($type, $start);
        $items = $this->items($team, $type, $from, $to)->groupBy('performance_plan_id');
        $recaps = RkRecap::forPeriod($team->id, $type, $from->toDateString())->with('lockedBy')->get()->keyBy('performance_plan_id');
        $context = $type === 'month' ? $this->weekParaphrases($team, $from, $to) : collect();

        return $this->teamPlans($team)
            ->map(function (PerformancePlan $plan) use ($items, $recaps, $context) {
                $planItems = $items->get($plan->id, collect());
                $recap = $recaps->get($plan->id);

                return [
                    'performance_plan_id' => $plan->id,
                    'rk_description' => $plan->description,
                    'project_id' => $plan->project_id,
                    'project_name' => $plan->project?->name,
                    'pic_employee_id' => $plan->pic_employee_id,
                    'entry_count' => $planItems->count(),
                    'entries' => $this->entries($planItems),
                    'recap' => $recap ? [
                        'paraphrase' => $recap->paraphrase,
                        'target' => $recap->target !== null ? (float) $recap->target : null,
                        'target_unit' => $recap->target_unit,
                        'capaian' => $recap->capaian !== null ? (float) $recap->capaian : null,
                        'locked' => $recap->isLocked(),
                        'locked_by' => $recap->lockedBy?->display_name ?? $recap->lockedBy?->name,
                    ] : null,
                    'default_unit' => $planItems->pluck('target_unit')->filter()->first() ?? $plan->target_unit,
                    'weeks' => $context->get($plan->id, []),
                ];
            })
            // RK with entries or a recap first, then alphabetical.
            ->sortBy(fn (array $panel) => [$panel['entry_count'] === 0 && $panel['recap'] === null ? 1 : 0, mb_strtolower($panel['rk_description'])])
            ->values()
            ->all();
    }

    /**
     * Team weekly recap rows, segmented by Projek.
     *
     * @return array<int, array<string, mixed>>
     */
    public function weekly(Team $team, string $weekStart): array
    {
        return $this->segments($team, 'week', Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY), $this->noOverrides());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function monthly(Team $team, int $year, int $month): array
    {
        return $this->segments($team, 'month', Carbon::create($year, $month, 1), $this->noOverrides());
    }

    /**
     * Quarterly recap (FRA): numbers from the months, text and follow-up from the PJ.
     *
     * @return array<int, array<string, mixed>>
     */
    public function quarterly(Team $team, int $year, int $quarter): array
    {
        $overrides = RecapOverride::query()
            ->with(['followUpPic', 'confirmedBy'])
            ->where('team_id', $team->id)
            ->where('period_type', 'quarter')
            ->where('period_year', $year)
            ->where('period_quarter', $quarter)
            ->get()
            ->keyBy(fn (RecapOverride $o) => self::rowKey($o->performance_plan_id, $o->project_id));

        return $this->segments($team, 'quarter', Carbon::create($year, ($quarter - 1) * 3 + 1, 1), $overrides);
    }

    /**
     * Per member: how many plan items they have in the week and how many already
     * carry a capaian.
     *
     * @return array<int, array{employee_id: int, name: string, total: int, saved: int, status: string}>
     */
    public function memberCompleteness(Team $team, string $weekStart): array
    {
        $start = Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY);
        $items = $this->items($team, 'week', $start, $start->copy()->endOfWeek(Carbon::SUNDAY))->groupBy('employee_id');

        return $team->members()->orderBy('employees.name')->get()->map(function (Employee $member) use ($items) {
            $mine = $items->get($member->id, collect());
            $total = $mine->count();
            $done = $mine->whereNotNull('capaian')->count();

            return [
                'employee_id' => $member->id,
                'name' => $member->display_name ?? $member->name,
                'total' => $total,
                'saved' => $done,
                'status' => match (true) {
                    $total === 0 => 'no_activity',
                    $done >= $total => 'complete',
                    $done === 0 => 'empty',
                    default => 'partial',
                },
            ];
        })->values()->all();
    }

    /** The week of the team's latest plan item, or null when there is none. */
    public function defaultWeekStart(Team $team): ?string
    {
        $latest = PlanItem::where('team_id', $team->id)->where('status', '!=', 'cancelled')->max('date_start');

        return $latest ? Carbon::parse($latest)->startOfWeek(Carbon::MONDAY)->toDateString() : null;
    }

    /** The month of the team's latest plan item. */
    public function latestPeriod(Team $team): ?Carbon
    {
        $latest = PlanItem::where('team_id', $team->id)->where('status', '!=', 'cancelled')->max('date_start');

        return $latest ? Carbon::parse($latest) : null;
    }

    /** Identity of one recap row: an RK within a Projek (project may be null). */
    public static function rowKey(int $planId, ?int $projectId): string
    {
        return $planId.':'.($projectId ?? '');
    }

    /** @return Collection<string, RecapOverride> */
    private function noOverrides(): Collection
    {
        return collect();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(string $type, Carbon $start): array
    {
        return match ($type) {
            'week' => [$start->copy()->startOfWeek(Carbon::MONDAY), $start->copy()->startOfWeek(Carbon::MONDAY)->endOfWeek(Carbon::SUNDAY)],
            'month' => [$start->copy()->startOfMonth(), $start->copy()->endOfMonth()],
            default => [$start->copy()->firstOfQuarter(), $start->copy()->lastOfQuarter()],
        };
    }

    /**
     * The team's plan items of a period. A week counts every item that overlaps
     * it, like the member's page; a month or quarter counts an item once, by its
     * start date, so sums never count a long item twice.
     *
     * @return Collection<int, PlanItem>
     */
    private function items(Team $team, string $type, Carbon $from, Carbon $to): Collection
    {
        return PlanItem::query()
            ->where('team_id', $team->id)
            ->where('status', '!=', 'cancelled')
            ->when(
                $type === 'week',
                fn (Builder $q) => $q->whereDate('date_start', '<=', $to->toDateString())->whereDate('date_end', '>=', $from->toDateString()),
                fn (Builder $q) => $q->whereDate('date_start', '>=', $from->toDateString())->whereDate('date_start', '<=', $to->toDateString()),
            )
            ->with(['employee:id,name,display_name', 'performancePlan.project', 'performancePlan.team', 'project'])
            ->orderBy('date_start')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, PerformancePlan> */
    private function teamPlans(Team $team): Collection
    {
        return PerformancePlan::with('project')
            ->where(fn (Builder $q) => $q->where('team_id', $team->id)->orWhereHas('project', fn (Builder $p) => $p->where('team_id', $team->id)))
            ->get();
    }

    /**
     * One entry per member: their sentences (the uraian capaian, else the
     * kegiatan), and their own target and capaian summed over their items.
     *
     * @param  Collection<int, PlanItem>  $items
     * @return list<array<string, mixed>>
     */
    private function entries(Collection $items): array
    {
        return $items->groupBy('employee_id')
            ->map(function (Collection $mine) {
                $sentences = $mine->flatMap(function (PlanItem $i) {
                    $own = self::sentences($i->capaian_note);

                    return $own->isNotEmpty() ? $own : self::sentences($i->description);
                });

                return [
                    'employee_id' => $mine->first()->employee_id,
                    'name' => $mine->first()->employee?->display_name ?? $mine->first()->employee?->name ?? 'Anggota',
                    'lines' => $sentences->values()->all(),
                    'items' => $mine->count(),
                    'target' => $mine->whereNotNull('target')->isEmpty() ? null : (float) $mine->sum('target'),
                    'capaian' => $mine->whereNotNull('capaian')->isEmpty() ? null : (float) $mine->sum('capaian'),
                    'unit' => $mine->pluck('target_unit')->filter()->first(),
                ];
            })
            ->sortBy(fn (array $entry) => mb_strtolower($entry['name']))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    public static function sentences(?string $text): Collection
    {
        return collect(preg_split('/(?<=[.!?])\s+|\R+/u', trim((string) $text)) ?: [])
            ->map(fn (string $s) => trim($s))
            ->filter(fn (string $s) => $s !== '' && $s !== '-')
            ->values();
    }

    /**
     * The weekly paraphrases of a month per RK, for the monthly panel to read.
     *
     * @return Collection<int, list<array{label: string, start: string, paraphrase: string}>>
     */
    private function weekParaphrases(Team $team, Carbon $from, Carbon $to): Collection
    {
        return RkRecap::query()
            ->where('team_id', $team->id)
            ->where('period_type', 'week')
            ->whereDate('period_start', '>=', $from->toDateString())
            ->whereDate('period_start', '<=', $to->toDateString())
            ->whereNotNull('paraphrase')
            ->orderBy('period_start')
            ->get()
            ->groupBy('performance_plan_id')
            ->map(fn (Collection $weeks) => $weeks->map(fn (RkRecap $w) => [
                'label' => Carbon::parse($w->period_start)->locale('id')->translatedFormat('j M'),
                'start' => Carbon::parse($w->period_start)->toDateString(),
                'paraphrase' => (string) $w->paraphrase,
            ])->values()->all());
    }

    /**
     * Rows grouped by Projek, then by RK.
     *
     * @param  Collection<string, RecapOverride>  $overrides
     * @return array<int, array<string, mixed>>
     */
    private function segments(Team $team, string $type, Carbon $start, Collection $overrides): array
    {
        [$from, $to] = $this->range($type, $start);
        $items = $this->items($team, $type, $from, $to)->groupBy('performance_plan_id');
        $recaps = $type === 'quarter'
            ? $this->monthRecaps($team, $from, $to)
            : RkRecap::forPeriod($team->id, $type, $from->toDateString())->with('lockedBy')->get()->groupBy('performance_plan_id');

        $planIds = $items->keys()->merge($recaps->keys())->unique();
        $plans = PerformancePlan::with('project', 'team')->whereIn('id', $planIds)->get();

        return $plans
            ->map(fn (PerformancePlan $plan) => $this->row($plan, $items->get($plan->id, collect()), $recaps->get($plan->id, collect()), $type, $overrides))
            ->groupBy(fn (array $row) => $row['project_id'] ?? 0)
            ->map(function (Collection $rows) {
                $project = $rows->first()['project_id'] ? Project::find($rows->first()['project_id']) : null;

                return [
                    'project_id' => $project?->id,
                    'project_name' => $project?->name ?? $rows->first()['team_name'] ?? '—',
                    // The ketua tim's RK the Projek hangs under (kipApp proyek.rencanakinerjaketua).
                    'leader_rk' => $project?->leader_rk,
                    'rows' => $rows->sortBy(fn (array $r) => mb_strtolower($r['rk_description']))->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The monthly recaps inside a quarter, per RK.
     *
     * @return Collection<int, Collection<int, RkRecap>>
     */
    private function monthRecaps(Team $team, Carbon $from, Carbon $to): Collection
    {
        return RkRecap::query()
            ->where('team_id', $team->id)
            ->where('period_type', 'month')
            ->whereDate('period_start', '>=', $from->toDateString())
            ->whereDate('period_start', '<=', $to->toDateString())
            ->get()
            ->groupBy('performance_plan_id');
    }

    /**
     * @param  Collection<int, PlanItem>  $items
     * @param  Collection<int, RkRecap>  $recaps  one for a week or month, up to three for a quarter
     * @param  Collection<string, RecapOverride>  $overrides
     * @return array<string, mixed>
     */
    private function row(PerformancePlan $plan, Collection $items, Collection $recaps, string $type, Collection $overrides): array
    {
        $project = $items->first()?->project ?? $plan->project;
        $projectId = $project?->id;

        // The PJ's own numbers win; without them the members' numbers are summed.
        $pjTarget = $recaps->whereNotNull('target');
        $pjCapaian = $recaps->whereNotNull('capaian');
        $target = $pjTarget->isNotEmpty() ? (float) $pjTarget->sum('target') : (float) $items->sum('target');
        $realization = $pjCapaian->isNotEmpty() ? (float) $pjCapaian->sum('capaian') : (float) $items->sum('capaian');
        $hasNumbers = $pjTarget->isNotEmpty() || $items->whereNotNull('target')->isNotEmpty();
        $unit = $recaps->pluck('target_unit')->filter()->last() ?? $items->pluck('target_unit')->filter()->first() ?? $plan->target_unit;

        $override = $overrides->get(self::rowKey($plan->id, $projectId)) ?? $overrides->get(self::rowKey($plan->id, null));
        $paraphrase = $type === 'quarter'
            ? $override?->uraian
            : $recaps->first()?->paraphrase;
        $locked = $type === 'quarter' ? $override?->confirmed_at !== null : $recaps->first()?->isLocked() === true;

        $row = [
            'row_key' => self::rowKey($plan->id, $projectId),
            'performance_plan_id' => $plan->id,
            'project_id' => $projectId,
            'team_name' => $plan->team?->name,
            'pic_employee_id' => $plan->pic_employee_id,
            'rk_code' => $plan->code,
            'rk_description' => $plan->description ?? '—',
            'target' => $target,
            'realization' => $realization,
            'achievement' => $hasNumbers && $target > 0 ? round($realization / $target * 100, 2) : null,
            'target_unit' => $unit,
            'uraian_aggregated' => self::joinLines($items->map(fn (PlanItem $i) => $i->capaian_note ?: $i->description)),
            'pj_uraian' => $paraphrase,
            'is_overridden' => filled($paraphrase),
            'is_confirmed' => $locked,
            'confirmed_by' => $type === 'quarter'
                ? ($override?->confirmedBy?->display_name ?? $override?->confirmedBy?->name)
                : ($recaps->first()?->lockedBy?->display_name ?? $recaps->first()?->lockedBy?->name),
            'contributors' => $items->map(fn (PlanItem $i) => $i->employee?->display_name ?? $i->employee?->name)->filter()->unique()->values()->all(),
            'obstacle' => $override?->obstacle,
            'solution' => $override?->solution,
            'follow_up_plan' => $override?->follow_up_plan,
        ];

        if ($type === 'quarter') {
            $row['follow_up_evidence_url'] = $override?->follow_up_evidence_url;
            $row['follow_up_pic'] = $override?->followUpPic?->display_name ?? $override?->followUpPic?->name;
            $row['follow_up_pic_employee_id'] = $override?->follow_up_pic_employee_id;
            $row['follow_up_deadline'] = $override?->follow_up_deadline?->toDateString();
        }

        return $row;
    }

    /**
     * @param  Collection<int, string|null>  $values
     */
    private static function joinLines(Collection $values): ?string
    {
        $joined = $values->filter(fn ($v) => filled($v))->map(fn ($v) => trim((string) $v))->implode("\n");

        return $joined === '' ? null : $joined;
    }
}
