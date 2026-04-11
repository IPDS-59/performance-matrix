<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkItem>
 */
class WorkItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'number' => $this->faker->unique()->numberBetween(1, 1000),
            'description' => $this->faker->sentence(),
        ];
    }
}
