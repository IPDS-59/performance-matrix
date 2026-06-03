<?php

namespace App\Actions\Teams;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

class SyncTeamMembersAction
{
    /**
     * Sync team members, preserving started_at for existing pivot rows.
     *
     * leader_id update rule: if exactly one member in $memberMap has role='leader',
     * update teams.leader_id to that employee. Otherwise leave it unchanged.
     *
     * @param  array<int, array{role: string, is_primary: bool}>  $memberMap  keyed by employee_id
     */
    public function execute(Team $team, array $memberMap): void
    {
        // Load existing started_at values to preserve them.
        $existing = DB::table('employee_team')
            ->where('team_id', $team->id)
            ->whereIn('employee_id', array_keys($memberMap))
            ->pluck('started_at', 'employee_id');

        $today = now()->toDateString();

        $enriched = [];
        foreach ($memberMap as $employeeId => $pivotData) {
            $enriched[$employeeId] = array_merge($pivotData, [
                'started_at' => $existing[$employeeId] ?? $today,
            ]);
        }

        $team->members()->sync($enriched);
        $team->refresh();

        // Update leader_id if exactly one leader is present in the submitted set.
        $leaders = array_filter($memberMap, fn ($p) => ($p['role'] ?? '') === 'leader');
        if (count($leaders) === 1) {
            $team->update(['leader_id' => array_key_first($leaders)]);
        }
    }
}
