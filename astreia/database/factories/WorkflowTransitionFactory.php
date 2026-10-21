<?php

namespace Database\Factories;

use App\Models\ProjectWorkflow;
use App\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkflowTransition> */
class WorkflowTransitionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workflow_id' => ProjectWorkflow::factory(),
            'from_stage_id' => fn (array $attributes) => ProjectWorkflow::whereKey($attributes['workflow_id'])->firstOrFail()->stages()->where('key', 'backlog')->valueOrFail('id'),
            'to_stage_id' => fn (array $attributes) => ProjectWorkflow::whereKey($attributes['workflow_id'])->firstOrFail()->stages()->where('key', 'development')->valueOrFail('id'),
            'required_permission_id' => null,
            'requires_reason' => false,
            'requires_signoff' => false,
        ];
    }
}
