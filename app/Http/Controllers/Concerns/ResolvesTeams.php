<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Employee;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** The teams a user may open, the selected team, and who is its PJ. */
trait ResolvesTeams
{
    /**
     * @return Collection<int, Team>
     */
    private function teamsFor(Request $request): Collection
    {
        // The head reads every team, as in the office-wide meeting. Read-only for them.
        if ($request->user()->hasAnyRole(['admin', 'head'])) {
            return Team::orderBy('name')->get();
        }

        $employee = $request->user()->employee;

        return $employee === null ? collect() : $employee->teams()->orderBy('teams.name')->get();
    }

    /**
     * The ?team in the request, else a team the employee leads, else the first.
     *
     * @param  Collection<int, Team>  $teams
     */
    private function selectedTeam(Request $request, Collection $teams, ?Employee $employee = null): ?Team
    {
        $requested = $request->query('team');

        if ($requested !== null) {
            return $teams->firstWhere('id', (int) $requested) ?? $teams->first();
        }

        if ($employee !== null) {
            $led = $teams->first(fn (Team $t) => $this->isPj($employee, $t->id));
            if ($led !== null) {
                return $led;
            }
        }

        return $teams->first();
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return array<int, array{id: int, name: string}>
     */
    private function teamOptions(Collection $teams): array
    {
        return $teams->map(fn (Team $t) => ['id' => $t->id, 'name' => $t->name])->all();
    }

    /**
     * PJ = team leader (teams.leader_id) or a member with the 'leader' pivot role.
     */
    private function isPj(Employee $employee, int $teamId): bool
    {
        return Team::where('id', $teamId)->where('leader_id', $employee->id)->exists()
            || $employee->teams()
                ->where('teams.id', $teamId)
                ->wherePivot('role', 'leader')
                ->exists();
    }
}
