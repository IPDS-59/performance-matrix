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
            'target' => $this->faker->randomElement([1, 1, 1, 2, 3, 4, 5, 10, 12]),
            'target_unit' => $this->faker->randomElement(['Kegiatan', 'Dokumen', 'Laporan', 'Paket']),
        ];
    }
}
