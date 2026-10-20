<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Role> */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'name' => fake()->unique()->jobTitle(), 'description' => fake()->sentence(), 'is_active' => true];
    }
}
