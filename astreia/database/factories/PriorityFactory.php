<?php

namespace Database\Factories;

use App\Models\Priority;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Priority> */
class PriorityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory()->core(),
            'code' => fake()->unique()->bothify('p#####'),
            'name' => 'Normal',
            'rank' => 1,
            'is_active' => true,
        ];
    }
}
