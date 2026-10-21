<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Resolution;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Resolution> */
class ResolutionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory()->core(),
            'code' => fake()->unique()->bothify('done-#####'),
            'name' => 'Done',
            'outcome' => 'success',
            'is_active' => true,
        ];
    }
}
