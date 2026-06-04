<?php

namespace Database\Factories;

use App\Models\PerformanceIndicator;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceIndicator>
 */
class PerformanceIndicatorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'year' => now()->year,
            'code' => $this->faker->optional()->bothify('IKU-##'),
            'name' => $this->faker->words(5, true),
            'target' => $this->faker->randomElement([1, 2, 5, 10, 100]),
            'target_unit' => $this->faker->randomElement(['Kegiatan', 'Dokumen', 'Laporan', '%']),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
