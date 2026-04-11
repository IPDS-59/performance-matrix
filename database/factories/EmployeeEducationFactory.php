<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeEducation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeEducation>
 */
class EmployeeEducationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'degree_front' => $this->faker->randomElement([null, 'Dr.', 'Prof.']),
            'degree_back' => $this->faker->randomElement([null, 'S.ST', 'S.Si', 'M.Si', 'M.Stat']),
            'institution' => $this->faker->company(),
            'field_of_study' => $this->faker->randomElement(['Statistika', 'Matematika', 'Informatika', 'Ekonomi']),
            'graduated_year' => $this->faker->year(),
            'is_highest' => false,
        ];
    }
}
