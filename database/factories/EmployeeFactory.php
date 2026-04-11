<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->name();

        return [
            'team_id' => Team::factory(),
            'user_id' => null,
            'name' => $name,
            'full_name' => $name,
            'employee_number' => $this->faker->unique()->numerify('##############'),
            'position' => $this->faker->jobTitle(),
            'office' => null,
            'display_name' => $name,
            'is_active' => true,
        ];
    }
}
