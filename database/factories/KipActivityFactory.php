<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\KipActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KipActivity>
 */
class KipActivityFactory extends Factory
{
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-30 days', 'now');

        return [
            'employee_id' => Employee::factory(),
            'external_id' => $this->faker->unique()->uuid(),
            'nip_lama' => $this->faker->numerify('#########'),
            'description' => $this->faker->sentence(),
            'activity_date_start' => $start->format('Y-m-d'),
            'activity_date_end' => $start->format('Y-m-d'),
            'time_start' => '08:00:00',
            'time_end' => '12:00:00',
            'evidence_url' => null,
            'rk_external_id' => null,
            'rk_name' => null,
            'progress' => null,
            'achievement_note' => null,
            'period_id' => null,
            'source_year' => null,
            'sent_at' => null,
            'raw_payload' => [],
            'fetched_at' => now(),
            'is_claimed' => false,
        ];
    }
}
