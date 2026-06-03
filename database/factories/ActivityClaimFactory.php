<?php

namespace Database\Factories;

use App\Models\ActivityClaim;
use App\Models\Employee;
use App\Models\PerformancePlan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityClaim>
 */
class ActivityClaimFactory extends Factory
{
    public function definition(): array
    {
        $dateStart = $this->faker->dateTimeBetween('-30 days', 'now');
        $carbon = Carbon::instance($dateStart);

        $target = $this->faker->randomFloat(2, 1, 100);
        $realization = $this->faker->randomFloat(2, 0, $target);

        return [
            'kip_activity_id' => null,
            'employee_id' => Employee::factory(),
            'performance_plan_id' => PerformancePlan::factory(),
            'work_item_id' => null,
            'target' => $target,
            'realization' => $realization,
            'achievement' => round($realization / $target * 100, 2),
            'target_unit' => $this->faker->randomElement(['Kegiatan', 'Dokumen', 'Laporan', 'Paket']),
            'obstacle' => null,
            'solution' => null,
            'follow_up_plan' => null,
            'activity_date_start' => $carbon->toDateString(),
            'activity_date_end' => null,
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'evidence_url' => null,
            'status' => 'draft',
            'week_start' => $carbon->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            'period_year' => (int) $carbon->year,
            'period_quarter' => (int) intdiv($carbon->month - 1, 3) + 1,
            'period_month' => (int) $carbon->month,
            'reserved_1' => null,
            'reserved_2' => null,
            'reserved_3' => null,
            'claimed_at' => null,
        ];
    }

    public function saved(): static
    {
        return $this->state(fn () => [
            'status' => 'saved',
            'claimed_at' => now(),
        ]);
    }
}
