<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'leader_id' => null,
            'name' => $this->faker->words(4, true),
            'description' => $this->faker->sentence(),
            'objective' => $this->faker->sentence(),
            'kpi' => $this->faker->sentence(),
            'status' => 'active',
            'year' => now()->year,
        ];
    }
}
