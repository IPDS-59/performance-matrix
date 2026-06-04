<?php

namespace Database\Factories;

use App\Models\PerformancePlan;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformancePlan>
 */
class PerformancePlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'code' => $this->faker->optional()->bothify('RK-##'),
            'description' => $this->faker->sentence(),
            'target' => $this->faker->randomElement([1, 2, 5, 10, 12]),
            'target_unit' => $this->faker->randomElement(['Kegiatan', 'Dokumen', 'Laporan', 'Paket']),
            'period_type' => 'year',
            'period' => null,
            'pic_employee_id' => null,
        ];
    }
}
