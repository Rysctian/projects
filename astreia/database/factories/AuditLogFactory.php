<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return ['actor_id' => User::factory(), 'project_id' => null, 'entity_type' => User::class, 'entity_id' => 1, 'action' => 'demo.created', 'before_json' => null, 'after_json' => ['source' => 'factory'], 'created_at' => now()];
    }
}
