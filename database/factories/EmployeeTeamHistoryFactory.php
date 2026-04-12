<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeTeamHistory;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeTeamHistory>
 */
class EmployeeTeamHistoryFactory extends Factory
{
    protected $model = EmployeeTeamHistory::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'team_id' => Team::factory(),
            'started_at' => $this->faker->dateTimeBetween('-2 years', '-6 months'),
            'ended_at' => null,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
