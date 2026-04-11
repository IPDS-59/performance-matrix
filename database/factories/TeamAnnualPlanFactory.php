<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamAnnualPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamAnnualPlan>
 */
class TeamAnnualPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'year' => now()->year,
            'kpi' => $this->faker->sentence(),
            'annual_plan' => $this->faker->paragraph(),
            'objective_1' => $this->faker->sentence(),
            'objective_2' => $this->faker->optional()->sentence(),
            'objective_3' => null,
        ];
    }
}
