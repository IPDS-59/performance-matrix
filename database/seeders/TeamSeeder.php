<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(File::get(database_path('seeders/data/seeder_data.json')), true);

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
