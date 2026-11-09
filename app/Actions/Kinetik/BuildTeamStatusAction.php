<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\PlanItem;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * For the PJ's beranda: per team they lead and per member, does this week have a plan and did last week get its realisasi.
 * One row per person, the ones who still owe something first.
 */
class BuildTeamStatusAction
{
    /**
     * @param  Collection<int, Team>  $teams
     * @return list<array<string, mixed>>
     */
    public function execute(Collection $teams, Carbon $today): array
    {
        $monday = $today->copy()->startOfWeek(Carbon::MONDAY);
        $lastMonday = $monday->copy()->subWeek();

        return $teams->map(function (Team $team) use ($monday, $lastMonday) {
            $now = $this->byMember($team, $monday);
            $last = $this->byMember($team, $lastMonday);
            $members = $team->members()->where('employees.is_active', true)->orderBy('employees.name')->get()->map(function (Employee $m) use ($now, $last) {
                $thisItems = $now->get($m->id, collect());
                $lastItems = $last->get($m->id, collect());
                $filled = $lastItems->whereNotNull('capaian')->count();

                return [
                    'employee_id' => $m->id,
                    'name' => $m->display_name ?? $m->name,
                    'plan_count' => $thisItems->count(),
                    'last_total' => $lastItems->count(),
                    'last_filled' => $filled,
                    'complete' => $thisItems->isNotEmpty() && $filled === $lastItems->count(),
                ];
            })->sortBy([['complete', 'asc'], ['name', 'asc']])->values();

            return [
                'team_id' => $team->id,
                'name' => $team->name,
                'week' => $monday->toDateString(),
                'complete' => $members->where('complete', true)->count(),
                'members' => $members->all(),
            ];
        })->values()->all();
    }

    /** @return Collection<int, Collection<int, PlanItem>> the team's kegiatan overlapping the week, per member */
    private function byMember(Team $team, Carbon $monday): Collection
    {
        return PlanItem::where('team_id', $team->id)
            ->duringWeek($monday->toDateString(), $monday->copy()->endOfWeek(Carbon::SUNDAY)->toDateString())
            ->get(['employee_id', 'capaian'])
            ->groupBy('employee_id');
    }
}
