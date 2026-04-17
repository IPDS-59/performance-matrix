<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamLeaderSeeder extends Seeder
{
    public function run(): void
    {
        $teams = Team::all();

        foreach ($teams as $team) {
            $topLeader = Project::where('team_id', $team->id)
                ->whereNotNull('leader_id')
                ->selectRaw('leader_id, COUNT(*) as project_count')
                ->groupBy('leader_id')
                ->orderByDesc('project_count')
                ->first();

            if ($topLeader) {
                $team->update(['leader_id' => $topLeader->leader_id]);
            }
        }
    }
}
