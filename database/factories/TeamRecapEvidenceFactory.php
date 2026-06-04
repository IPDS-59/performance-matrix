<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamRecapEvidence;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamRecapEvidence>
 */
class TeamRecapEvidenceFactory extends Factory
{
    public function definition(): array
    {
        $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);

        return [
            'team_id' => Team::factory(),
            'project_id' => null,
            'period_type' => 'week',
            'period_year' => (int) $weekStart->year,
            'week_start' => $weekStart->toDateString(),
            'period_quarter' => null,
            'period_month' => null,
            'type' => $this->faker->randomElement(['notula', 'photo', 'attendance']),
            'title' => $this->faker->sentence(3),
            'url' => $this->faker->url(),
            'uploaded_by' => null,
        ];
    }
}
