<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\WorkType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkType> */
class WorkTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory()->core(),
            'code' => fake()->unique()->bothify('task-#####'),
            'name' => 'Task',
            'is_active' => true,
        ];
    }
}
