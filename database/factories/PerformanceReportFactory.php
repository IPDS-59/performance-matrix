<?php

namespace Database\Factories;

use App\Models\PerformanceReport;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceReport>
 */
class PerformanceReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'work_item_id' => WorkItem::factory(),
            'reported_by' => null,
            'period_month' => $this->faker->numberBetween(1, 12),
            'period_year' => now()->year,
            'achievement_percentage' => $this->faker->randomFloat(2, 0, 100),
            'issues' => $this->faker->optional()->sentence(),
            'solutions' => $this->faker->optional()->sentence(),
            'action_plan' => $this->faker->optional()->sentence(),
        ];
    }
}
