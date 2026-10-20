<?php

namespace Database\Factories;

use App\Models\ProjectWorkflow;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkflowStage> */
class WorkflowStageFactory extends Factory
{
    public function definition(): array
    {
        return ['workflow_id' => ProjectWorkflow::factory(), 'key' => fake()->unique()->slug(2, '_'), 'name' => fake()->words(2, true), 'type' => 'ready', 'sort_order' => 0, 'is_initial' => false, 'is_active' => true];
    }
}
