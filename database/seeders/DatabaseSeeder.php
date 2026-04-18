<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TeamSeeder::class,
            EmployeeSeeder::class,
            ProjectSeeder::class,
            TeamLeaderSeeder::class,
            RolePermissionSeeder::class,
            UserSeeder::class,
        ]);
    }
}
