<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/seeder_data.json');
        if (! File::exists($path)) {
            $this->command->warn('seeder_data.json not found — skipping TeamSeeder');

            return;
        }

        $data = json_decode(File::get($path), true);

        foreach ($data['teams'] as $team) {
            Team::firstOrCreate(
                ['code' => $team['code']],
                [
                    'name' => $team['name'],
                    'is_active' => true,
                ]
            );
        }
    }
}
