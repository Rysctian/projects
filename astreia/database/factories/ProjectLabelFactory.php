<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectLabel> */
class ProjectLabelFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'name' => fake()->unique()->words(2, true)];
    }
}
