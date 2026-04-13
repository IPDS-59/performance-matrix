<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Team;
use App\Models\TeamAnnualPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/seeder_data.json');
        if (! File::exists($path)) {
            $this->command->warn('seeder_data.json not found — skipping ProjectSeeder');

            return;
        }

        $data = json_decode(File::get($path), true);

        // Build team annual plans: collect unique objectives per team
        $teamObjectives = [];
        foreach ($data['projects'] as $project) {
            $teamName = trim(preg_replace('/\s+/', ' ', $project['team']));
            if (! isset($teamObjectives[$teamName])) {
                $teamObjectives[$teamName] = [];
            }
            foreach ($project['objectives'] ?? [] as $obj) {
                if ($obj && ! in_array($obj, $teamObjectives[$teamName])) {
                    $teamObjectives[$teamName][] = $obj;
                }
            }
        }

        foreach ($teamObjectives as $teamName => $objectives) {
            $team = Team::where('name', $teamName)->first();
            if (! $team) {
                continue;
            }
            TeamAnnualPlan::firstOrCreate(
                ['team_id' => $team->id, 'year' => 2026],
                [
                    'objective_1' => $objectives[0] ?? null,
                    'objective_2' => $objectives[1] ?? null,
                    'objective_3' => $objectives[2] ?? null,
                ]
            );
        }

        // Seed projects
        foreach ($data['projects'] as $projectData) {
            $teamName = trim(preg_replace('/\s+/', ' ', $projectData['team']));
            $team = Team::where('name', $teamName)->first();
            if (! $team) {
                continue;
            }

            $leader = $projectData['leader']
                ? Employee::where('name', $projectData['leader'])->first()
                : null;

            $project = Project::firstOrCreate(
                ['name' => $projectData['name'], 'year' => $projectData['year']],
                [
                    'team_id' => $team->id,
                    'leader_id' => $leader?->id,
                    'objective' => $projectData['objective'] ?? null,
                    'kpi' => $projectData['kpi'] ?? null,
                    'status' => 'active',
                ]
            );

            if ($project->wasRecentlyCreated) {
                // Attach leader as project_member with role=leader
                if ($leader) {
                    $project->members()->syncWithoutDetaching([
                        $leader->id => ['role' => 'leader'],
                    ]);
                }

                // Attach members
                foreach ($projectData['members'] ?? [] as $memberName) {
                    $member = Employee::where('name', $memberName)->first();
                    if ($member) {
                        $project->members()->syncWithoutDetaching([
                            $member->id => ['role' => 'member'],
                        ]);
                    }
                }
            }
        }
    }
}
