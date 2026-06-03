<?php

namespace Database\Factories;

use App\Models\PerformancePlan;
use App\Models\RecapOverride;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecapOverride>
 */
class RecapOverrideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'performance_plan_id' => PerformancePlan::factory(),
            'period_type' => 'month',
            'period_year' => (int) now()->year,
            'period_quarter' => null,
            'period_month' => (int) now()->month,
            'obstacle' => $this->faker->sentence(),
            'solution' => $this->faker->sentence(),
            'follow_up_plan' => $this->faker->sentence(),
            'follow_up_evidence_url' => null,
            'follow_up_pic_employee_id' => null,
            'follow_up_deadline' => null,
            'created_by' => null,
        ];
    }
}
