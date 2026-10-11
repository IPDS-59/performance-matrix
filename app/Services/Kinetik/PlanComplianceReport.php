<?php

namespace App\Services\Kinetik;

use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PlanItem;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Planning compliance per team and week of a quarter: how many members made a
 * plan, and for finished weeks how the plans turned out (same rule as the
 * Friday evaluation).
 */
class PlanComplianceReport
{
    public function __construct(private readonly PlanEvaluator $evaluator) {}

    /**
     * @param  Collection<int, Team>  $teams
     * @return array{weeks: list<string>, teams: list<array<string, mixed>>}
     */
    public function build(Collection $teams, Carbon $today): array
    {
        $thisMonday = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weeks = [];
        for ($monday = $today->copy()->firstOfQuarter()->startOfWeek(Carbon::MONDAY); $monday->lte($thisMonday); $monday->addWeek()) {
            $weeks[] = $monday->copy();
        }
        $quarterStart = $weeks[0]->toDateString();
        $quarterEnd = $thisMonday->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        return [
            'weeks' => array_map(fn (Carbon $w) => $w->toDateString(), $weeks),
            'teams' => $teams->map(fn (Team $team) => $this->team($team, $weeks, $thisMonday, $quarterStart, $quarterEnd))->values()->all(),
        ];
    }

    /**
     * @param  list<Carbon>  $weeks
     * @return array<string, mixed>
     */
    private function team(Team $team, array $weeks, Carbon $thisMonday, string $from, string $to): array
    {
        $members = $team->members()->where('employees.is_active', true)->orderBy('employees.name')->get();
        $plans = PlanItem::where('team_id', $team->id)->duringWeek($from, $to)
            ->with('performancePlan:id,description,kip_external_id')->get();
        $activities = $plans->isEmpty() ? collect() : KipActivity::whereIn('employee_id', $plans->pluck('employee_id')->unique())
            ->duringWeek($from, $to)
            ->get(['id', 'employee_id', 'activity_date_start', 'activity_date_end', 'progress', 'rk_external_id', 'rk_name'])
            ->groupBy('employee_id');

        $rows = [];
        $missing = [];
        foreach ($weeks as $monday) {
            $start = $monday->toDateString();
            $end = $monday->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
            $inWeek = $plans->filter(fn (PlanItem $p) => substr((string) $p->date_start, 0, 10) <= $end && substr((string) $p->date_end, 0, 10) >= $start);
            $planners = $members->filter(fn (Employee $m) => $inWeek->contains('employee_id', $m->id));

            $row = ['week_start' => $start, 'members' => $members->count(), 'planners' => $planners->count(), 'plans' => $inWeek->count(), 'done' => null, 'in_progress' => null, 'not_started' => null];

            // Outcomes only for finished weeks: the current week is still open.
            if ($monday->lt($thisMonday)) {
                $states = $inWeek->map(fn (PlanItem $p) => $this->evaluator->evaluate($p, $activities->get($p->employee_id, collect()))['state']);
                $row['done'] = $states->filter(fn ($s) => $s === 'done')->count();
                $row['in_progress'] = $states->filter(fn ($s) => $s === 'in_progress')->count();
                $row['not_started'] = $states->filter(fn ($s) => $s === 'not_started')->count();
            } else {
                $missing = $members->reject(fn (Employee $m) => $planners->contains('id', $m->id))
                    ->map(fn (Employee $m) => $m->display_name ?? $m->name)->values()->all();
            }

            $rows[] = $row;
        }

        return ['team_id' => $team->id, 'name' => $team->name, 'weeks' => $rows, 'missing_this_week' => $missing];
    }
}
