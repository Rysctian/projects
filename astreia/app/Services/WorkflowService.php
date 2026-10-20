<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectWorkflow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    public const TYPES = ['intake', 'ready', 'work', 'review', 'verification', 'pre_close', 'closed'];

    /**
     * @return array{
     *     stages: list<array{key: string, name: string, type: string, sort_order: int, is_initial: bool, is_active: bool}>,
     *     transitions: list<array{from_key: string, to_key: string, required_permission_key: string, requires_reason: bool, requires_signoff: bool}>
     * }
     */
    public static function template(): array
    {
        $stages = [];
        foreach (['backlog' => 'intake', 'ready' => 'ready', 'development' => 'work', 'qa' => 'verification', 'closed' => 'closed'] as $key => $type) {
            $stages[] = ['key' => $key, 'name' => $key === 'qa' ? 'QA' : ucfirst($key), 'type' => $type, 'sort_order' => count($stages), 'is_initial' => $key === 'backlog', 'is_active' => true];
        }
        $transitions = [];
        foreach ([['backlog', 'ready'], ['ready', 'development'], ['development', 'qa'], ['qa', 'closed'], ['qa', 'development']] as [$from, $to]) {
            $transitions[] = ['from_key' => $from, 'to_key' => $to, 'required_permission_key' => $to === 'closed' ? 'tickets.close' : 'tickets.transition', 'requires_reason' => $from === 'qa' && $to === 'development', 'requires_signoff' => $to === 'closed'];
        }

        return compact('stages', 'transitions');
    }

    public function initialize(Project $project, User $actor): void
    {
        $draft = $this->saveDraft($project, $actor, self::template());
        $this->publish($project, $actor, $draft);
    }

    /** @param array<string, mixed> $input */
    public function saveDraft(Project $project, User $actor, array $input): ProjectWorkflow
    {
        return DB::transaction(function () use ($project, $actor, $input) {
            $project = Project::lockForUpdate()->findOrFail($project->id);
            ProjectAccess::authorize($actor, $project, 'workflows.manage');
            $data = Validator::make($input, [
                'workflow_id' => 'nullable|integer', 'stages' => 'required|array|min:1|max:50',
                'stages.*.key' => ['required', 'regex:/^[a-z][a-z0-9_]{0,59}$/', 'distinct'],
                'stages.*.name' => 'required|string|max:100', 'stages.*.type' => ['required', Rule::in(self::TYPES)],
                'stages.*.sort_order' => 'required|integer|min:0', 'stages.*.is_initial' => 'required|boolean', 'stages.*.is_active' => 'required|boolean',
                'transitions' => 'present|array|max:500', 'transitions.*.from_key' => 'required|string', 'transitions.*.to_key' => 'required|string',
                'transitions.*.required_permission_key' => ['nullable', Rule::in(ProjectAccess::KEYS)],
                'transitions.*.requires_reason' => 'required|boolean', 'transitions.*.requires_signoff' => 'required|boolean',
            ])->validate();
            $keys = array_column($data['stages'], 'key');
            $edges = [];
            foreach ($data['transitions'] as $transition) {
                $edge = $transition['from_key'].'>'.$transition['to_key'];
                if (! in_array($transition['from_key'], $keys, true) || ! in_array($transition['to_key'], $keys, true) || $transition['from_key'] === $transition['to_key'] || isset($edges[$edge])) {
                    throw ValidationException::withMessages(['transitions' => 'Transitions must connect distinct stages in this workflow without duplicate edges.']);
                }
                $edges[$edge] = true;
            }
            $workflow = ! empty($data['workflow_id']) ? $project->workflows()->whereKey($data['workflow_id'])->firstOrFail() : null;
            if ($workflow && $workflow->state !== 'draft') {
                throw ValidationException::withMessages(['workflow_id' => 'Published workflow versions are immutable. Create a new draft.']);
            }
            $workflow ??= $project->workflows()->create(['version_number' => ((int) $project->workflows()->max('version_number')) + 1, 'state' => 'draft']);
            $workflow->transitions()->delete();
            $workflow->stages()->delete();
            $stageIds = [];
            foreach ($data['stages'] as $stage) {
                $stageIds[$stage['key']] = $workflow->stages()->create($stage)->id;
            }
            foreach ($data['transitions'] as $transition) {
                $permission = empty($transition['required_permission_key']) ? null : Permission::where('key', $transition['required_permission_key'])->where('scope', 'project')->firstOrFail();
                $workflow->transitions()->create([
                    'from_stage_id' => $stageIds[$transition['from_key']], 'to_stage_id' => $stageIds[$transition['to_key']],
                    'required_permission_id' => $permission?->id, 'requires_reason' => $transition['requires_reason'], 'requires_signoff' => $transition['requires_signoff'],
                ]);
            }
            AuditLog::record($actor, $workflow, 'workflow.drafted', after: $data, projectId: $project->id);

            return $workflow;
        });
    }

    public function publish(Project $project, User $actor, ProjectWorkflow $workflow): ProjectWorkflow
    {
        return DB::transaction(function () use ($project, $actor, $workflow) {
            $project = Project::lockForUpdate()->findOrFail($project->id);
            ProjectAccess::authorize($actor, $project, 'workflows.manage');
            $workflow = $project->workflows()->lockForUpdate()->findOrFail($workflow->id);
            if ($workflow->state !== 'draft') {
                throw ValidationException::withMessages(['workflow_id' => 'Only a draft can be published.']);
            }
            $this->validateGraph($workflow);
            $before = ['current_workflow_id' => $project->current_workflow_id];
            $workflow->update(['state' => 'published', 'published_at' => now()]);
            $project->update(['current_workflow_id' => $workflow->id]);
            AuditLog::record($actor, $workflow, 'workflow.published', $before, ['current_workflow_id' => $workflow->id], $project->id);

            return $workflow;
        });
    }

    private function validateGraph(ProjectWorkflow $workflow): void
    {
        $stages = $workflow->stages()->where('is_active', true)->get();
        $initial = $stages->where('is_initial', true);
        $closed = $stages->where('type', 'closed');
        $fail = fn () => throw ValidationException::withMessages(['workflow' => 'Use exactly one active intake initial stage. Every active stage must be reachable from intake and have a path to closure. Closed stages have no outgoing transitions.']);
        if ($initial->count() !== 1 || $initial->first()->type !== 'intake' || $closed->isEmpty() || ! $stages->contains('type', 'verification') || $workflow->stages()->where('is_initial', true)->count() !== 1) {
            $fail();
        }
        $edges = $workflow->transitions()->get();
        foreach ($edges as $edge) {
            if (! $stages->contains('id', $edge->from_stage_id) || ! $stages->contains('id', $edge->to_stage_id) || $closed->contains('id', $edge->from_stage_id)) {
                $fail();
            }
            if ($edge->requires_signoff && ! in_array($stages->firstWhere('id', $edge->from_stage_id)->type, ['review', 'verification', 'pre_close'], true)) {
                throw ValidationException::withMessages(['workflow' => 'Sign-off gates must leave a review, verification, or pre-close checkpoint.']);
            }
        }
        $walk = function (array $start, bool $reverse = false) use ($edges): array {
            $seen = $start;
            for ($i = 0; $i < count($seen); $i++) {
                foreach ($edges as $edge) {
                    $from = $reverse ? $edge->to_stage_id : $edge->from_stage_id;
                    $to = $reverse ? $edge->from_stage_id : $edge->to_stage_id;
                    if ($from === $seen[$i] && ! in_array($to, $seen, true)) {
                        $seen[] = $to;
                    }
                }
            }

            return $seen;
        };
        if (count($walk([$initial->first()->id])) !== $stages->count() || count($walk($closed->pluck('id')->all(), true)) !== $stages->count()) {
            $fail();
        }
    }
}
