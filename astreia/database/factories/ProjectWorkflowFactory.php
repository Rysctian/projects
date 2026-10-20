<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectWorkflow;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProjectWorkflow> */
class ProjectWorkflowFactory extends Factory
{
    public function definition(): array
    {
        return ['project_id' => Project::factory(), 'version_number' => 1, 'state' => 'published', 'published_at' => now()];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (ProjectWorkflow $workflow): void {
            $stageIds = [];
            foreach (WorkflowService::template()['stages'] as $stage) {
                $stageIds[$stage['key']] = $workflow->stages()->create($stage)->id;
            }
            foreach (WorkflowService::template()['transitions'] as $transition) {
                $workflow->transitions()->create([
                    'from_stage_id' => $stageIds[$transition['from_key']],
                    'to_stage_id' => $stageIds[$transition['to_key']],
                    'required_permission_id' => null,
                    'requires_reason' => $transition['requires_reason'],
                    'requires_signoff' => $transition['requires_signoff'],
                ]);
            }
            Project::whereKey($workflow->project_id)->whereNull('current_workflow_id')->update(['current_workflow_id' => $workflow->id]);
        });
    }
}
