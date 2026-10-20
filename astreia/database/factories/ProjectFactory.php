<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->bothify('PRJ###')), 'name' => fake()->company().' Workspace', 'description' => fake()->paragraph(), 'status' => 'active', 'created_by' => User::factory(), 'archived_at' => null, 'current_workflow_id' => null];
    }
}
