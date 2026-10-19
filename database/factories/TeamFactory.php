<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            // Unique: every EmployeeFactory row makes a team, and three random letters collided now and then.
            'code' => strtoupper($this->faker->unique()->bothify('???###')),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }
}
