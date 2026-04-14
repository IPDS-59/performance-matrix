<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeEducation;
use App\Models\EmployeeTeamHistory;
use App\Models\Team;
use Database\Seeders\Concerns\StripsFrontDegree;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class EmployeeSeeder extends Seeder
{
    use StripsFrontDegree;

    public function run(): void
    {
        $path = database_path('seeders/data/seeder_data.prod.json');
        if (! File::exists($path)) {
            $path = database_path('seeders/data/seeder_data.json');
        }
        if (! File::exists($path)) {
            $this->command->warn('seeder_data.json not found — skipping EmployeeSeeder');

            return;
        }

        $data = json_decode(File::get($path), true);

        foreach ($data['employees'] as $emp) {
            $teamName = trim(preg_replace('/\s+/', ' ', $emp['team']));
            $team = Team::where('name', $teamName)->first();

            $degreeBack = $emp['degree_back'] ?? null;
            [$degreeFront, $cleanName] = $this->extractFrontDegree($emp['name']);
            $displayName = $this->buildDisplayName($cleanName, $degreeFront, $degreeBack);

            $employee = Employee::firstOrCreate(
                ['name' => $cleanName],
                [
                    'team_id' => $team?->id,
                    'full_name' => $cleanName,
                    'position' => null,
                    'display_name' => $displayName,
                    'is_active' => true,
                ]
            );

            // Seed education if any degree present
            if (($degreeFront || $degreeBack) && $employee->wasRecentlyCreated) {
                EmployeeEducation::create([
                    'employee_id' => $employee->id,
                    'degree_front' => $degreeFront,
                    'degree_back' => $degreeBack,
                    'is_highest' => true,
                ]);
            }

            // Seed current team history
            if ($team && $employee->wasRecentlyCreated) {
                EmployeeTeamHistory::create([
                    'employee_id' => $employee->id,
                    'team_id' => $team->id,
                    'started_at' => '2026-01-01',
                    'ended_at' => null,
                ]);
            }
        }
    }

    private function buildDisplayName(string $name, ?string $degreeFront, ?string $degreeBack): string
    {
        $parts = [];
        if ($degreeFront) {
            $parts[] = $degreeFront.' '.$name;
        } else {
            $parts[] = $name;
        }
        if ($degreeBack) {
            $parts[] = $degreeBack;
        }

        return implode(', ', $parts);
    }
}
