<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Permission> */
class PermissionFactory extends Factory
{
    public function definition(): array
    {
        return ['key' => 'demo.'.fake()->unique()->slug(2), 'description' => fake()->sentence(), 'scope' => 'project'];
    }
}
