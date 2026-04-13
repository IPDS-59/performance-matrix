<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class WorkItemSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/seeder_data.prod.json');
        if (! File::exists($path)) {
            $path = database_path('seeders/data/seeder_data.json');
        }
        if (! File::exists($path)) {
            $this->command->warn('seeder_data.json not found — skipping WorkItemSeeder');

            return;
        }

        $data = json_decode(File::get($path), true);

        foreach ($data['work_items'] ?? [] as $item) {
            $project = Project::where('name', $item['project'])->where('year', $item['year'] ?? 2026)->first();
            if (! $project) {
                continue;
            }

            WorkItem::firstOrCreate(
                ['project_id' => $project->id, 'number' => $item['number']],
                ['description' => $item['description']]
            );
        }
    }
}
