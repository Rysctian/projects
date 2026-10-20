<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectWorkflow;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ticket> */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'number' => 1,
            'module_id' => null,
            'workflow_id' => fn (array $attributes) => ProjectWorkflow::factory()->create(['project_id' => $attributes['project_id']])->id,
            'stage_id' => fn (array $attributes) => WorkflowStage::where('workflow_id', $attributes['workflow_id'])->where('key', 'backlog')->valueOrFail('id'),
            'revision_number' => 1,
            'lock_version' => 1,
            'stage_visit' => 1,
            'verification_cycle' => 1,
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'category' => 'support',
            'priority' => 'normal',
            'is_intake_stub' => false,
            'created_by' => User::factory(),
            'assigned_to' => null,
            'estimate_minutes' => fake()->numberBetween(30, 480),
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'acceptance_criteria' => [fake()->sentence()],
            'completion_notes' => null,
            'documentation_impact' => null,
            'documentation_impact_note' => null,
            'is_blocked' => false,
            'is_cancelled' => false,
            'blocked_from_stage_id' => null,
            'closed_by' => null,
            'closed_at' => null,
        ];
    }

    public function inStage(string $stageKey): static
    {
        return $this->afterCreating(function (Ticket $ticket) use ($stageKey): void {
            $ticket->update(['stage_id' => $ticket->workflow->stages()->where('key', $stageKey)->valueOrFail('id')]);
        });
    }
}
