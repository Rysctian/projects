<?php

namespace Database\Factories;

use App\Models\Module;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Module> */
class ModuleFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'code' => strtoupper(fake()->unique()->bothify('MOD##')), 'name' => fake()->words(2, true), 'status' => 'active'];
    }
}
