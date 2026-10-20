<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketService
{
    /** @param array<string, mixed> $input */
    public function create(Project $project, User $actor, array $input): Ticket
    {
        return DB::transaction(function () use ($project, $actor, $input) {
            $project = Project::lockForUpdate()->findOrFail($project->id);
            ProjectAccess::authorize($actor, $project, 'tickets.create');
            $data = $this->validateFields($project, $input, true);
            if (! empty($data['assigned_to'])) {
                ProjectAccess::authorize($actor, $project, 'tickets.assign');
                $this->eligibleAssignee($project, (int) $data['assigned_to']);
            }
            $workflow = $project->workflows()->where('id', $project->current_workflow_id)->where('state', 'published')->first();
            $this->require($workflow !== null, 'workflow', 'Publish a project workflow before creating tickets.');
            $initial = $workflow->stages()->where('is_initial', true)->where('is_active', true)->firstOrFail();
            DB::table('project_ticket_counters')->insertOrIgnore(['project_id' => $project->id, 'next_number' => 1]);
            $counter = DB::table('project_ticket_counters')->where('project_id', $project->id)->lockForUpdate()->first();
            DB::table('project_ticket_counters')->where('project_id', $project->id)->update(['next_number' => $counter->next_number + 1]);
            unset($data['label_ids']);
            $ticket = $project->tickets()->create([
                ...$data, 'number' => $counter->next_number, 'workflow_id' => $workflow->id,
                'stage_id' => $initial->id, 'created_by' => $actor->id,
                'is_intake_stub' => empty($data['title']) || empty($data['description']),
            ]);
            $this->activity($ticket, $actor, 'created', ['key' => $ticket->key, 'stage' => $initial->name, 'to_stage_id' => $initial->id, 'to_visit' => 1, 'incomplete_intake' => $ticket->is_intake_stub]);

            return $ticket;
        });
    }

    /** @param array<string, mixed> $input */
    public function update(Project $project, User $actor, Ticket $ticket, array $input): Ticket
    {
        return $this->mutate($project, $actor, $ticket, $input, function (Ticket $ticket, Project $project) use ($actor, $input) {
            $this->editable($ticket);
            $merged = [...$ticket->only(['title', 'description', 'category', 'priority', 'module_id', 'assigned_to', 'estimate_minutes', 'due_date', 'acceptance_criteria']), ...$input, 'is_intake_stub' => $ticket->is_intake_stub];
            if (! array_key_exists('due_date', $input)) {
                $merged['due_date'] = $ticket->due_date?->format('Y-m-d');
            }
            $data = $this->validateFields($project, $merged, false);
            $assignmentChanged = ($data['assigned_to'] ?? null) != $ticket->assigned_to;
            $comparison = clone $ticket;
            $comparison->fill(collect($data)->except(['assigned_to', 'label_ids', 'is_intake_stub'])->all());
            $fieldsChanged = $comparison->isDirty();
            if ($fieldsChanged || array_key_exists('label_ids', $data)) {
                ProjectAccess::authorize($actor, $project, 'tickets.edit');
            }
            if ($assignmentChanged) {
                ProjectAccess::authorize($actor, $project, 'tickets.assign');
            }
            if (! $fieldsChanged && ! $assignmentChanged && ! array_key_exists('label_ids', $data)) {
                ProjectAccess::authorize($actor, $project, 'tickets.edit');
            }
            if (! empty($data['assigned_to'])) {
                $this->eligibleAssignee($project, (int) $data['assigned_to']);
            }
            $before = $ticket->only(['title', 'description', 'category', 'priority', 'module_id', 'assigned_to', 'estimate_minutes', 'due_date', 'acceptance_criteria', 'revision_number']);
            $labelIds = $data['label_ids'] ?? null;
            unset($data['label_ids'], $data['is_intake_stub']);
            $ticket->fill($data);
            $ticket->is_intake_stub = ! filled($ticket->title) || ! filled($ticket->description);
            if ($ticket->isDirty(['title', 'description', 'category', 'module_id', 'acceptance_criteria'])) {
                $ticket->revision_number++;
                $ticket->verification_cycle++;
            }
            if ($ticket->stage->type !== 'intake') {
                $this->ready($ticket, $project);
            }
            if ($labelIds !== null) {
                $ticket->labels()->sync(collect(array_values($labelIds))->mapWithKeys(fn ($id) => [$id => ['project_id' => $project->id]])->all());
            }
            $this->activity($ticket, $actor, 'updated', ['before' => $before, 'after' => $ticket->only(array_keys($before)), 'labels' => $labelIds]);
        });
    }

    /** @param array<string, mixed> $input */
    public function transition(Project $project, User $actor, Ticket $ticket, array $input): Ticket
    {
        return $this->mutate($project, $actor, $ticket, $input, function (Ticket $ticket, Project $project) use ($actor, $input) {
            ProjectAccess::authorize($actor, $project, 'tickets.transition');
            $this->editable($ticket);
            $this->require(! $ticket->is_blocked, 'stage_id', 'Resume the blocked ticket before advancing it.');
            $data = Validator::make($input, [
                'transition_id' => 'required|integer', 'reason' => 'nullable|string|max:10000', 'completion_notes' => 'nullable|string|max:20000',
                'documentation_impact' => ['nullable', Rule::in(['required', 'not_required'])], 'documentation_impact_note' => 'nullable|string|max:10000',
            ])->validate();
            $edge = $ticket->workflow->transitions()->where('id', $data['transition_id'])->where('from_stage_id', $ticket->stage_id)->with(['toStage', 'requiredPermission'])->first();
            $this->require($edge !== null, 'transition_id', 'That transition is not allowed from the current stage.');
            $this->require($edge->toStage->is_active, 'transition_id', 'The destination stage is inactive.');
            if ($edge->requiredPermission) {
                ProjectAccess::authorize($actor, $project, $edge->requiredPermission->key);
            }
            if ($ticket->stage->type === 'work' && ! $actor->is_system_admin && ! $actor->hasProjectPermission($project, 'tickets.assign')) {
                $this->require($ticket->assigned_to === $actor->id && $actor->hasProjectPermission($project, 'tickets.work'), 'assigned_to', 'Only the eligible assignee or an authorized assignment manager can advance work.');
            }
            if ($edge->requires_reason) {
                $this->require(filled($data['reason'] ?? null), 'reason', 'A reason is required for this transition.');
            }
            if ($edge->toStage->type !== 'intake') {
                $this->ready($ticket, $project);
            } else {
                $this->require(! $ticket->is_intake_stub, 'title', 'Complete the ticket title and description before advancing.');
            }
            $currentSignoffs = $ticket->signoffs()->where('verified_revision', $ticket->revision_number)->where('verification_cycle', $ticket->verification_cycle)
                ->where('stage_id', $ticket->stage_id)->where('stage_visit', $ticket->stage_visit)->latest('id')->get();
            $latest = $currentSignoffs->first();
            $returnPath = in_array($ticket->stage->type, ['review', 'verification', 'pre_close'], true)
                && in_array($edge->toStage->type, ['intake', 'ready', 'work', 'review'], true)
                && ($edge->toStage->type !== 'review' || $edge->toStage->sort_order <= $ticket->stage->sort_order);
            if ($latest?->result === 'fail') {
                $this->require($returnPath && filled($data['reason'] ?? null), 'transition_id', 'Failed verification requires a reason and a configured return path.');
            }
            if ($edge->requires_signoff && ! ($returnPath && $latest?->result === 'fail')) {
                $this->require($latest?->result === 'pass', 'signoff', 'Record a current passing sign-off before leaving this checkpoint.');
            }
            if ($edge->toStage->type === 'closed') {
                ProjectAccess::authorize($actor, $project, 'tickets.close');
                $this->closure($ticket, $data);
                $ticket->completion_notes = $data['completion_notes'];
                $ticket->documentation_impact = $data['documentation_impact'] ?? null;
                $ticket->documentation_impact_note = $data['documentation_impact_note'] ?? null;
                $ticket->closed_by = $actor->id;
                $ticket->closed_at = now();
            }
            $from = $ticket->stage;
            $fromVisit = $ticket->stage_visit;
            $ticket->stage_id = $edge->to_stage_id;
            $ticket->stage_visit++;
            $ticket->unsetRelation('stage');
            $this->activity($ticket, $actor, 'transitioned', [
                'from' => $from->name, 'to' => $edge->toStage->name, 'from_stage_id' => $from->id, 'to_stage_id' => $ticket->stage_id,
                'from_visit' => $fromVisit, 'to_visit' => $ticket->stage_visit, 'reason' => $data['reason'] ?? null, 'requires_signoff' => $edge->requires_signoff,
                'invalidated_stage_ids' => $returnPath ? $ticket->workflow->stages()
                    ->whereIn('type', ['review', 'verification', 'pre_close'])
                    ->where(fn ($query) => $query->where('type', 'verification')->orWhere('sort_order', '>=', $edge->toStage->sort_order))->pluck('id')->all() : [],
            ]);
        });
    }

    /** @param array<string, mixed> $input */
    public function signoff(Project $project, User $actor, Ticket $ticket, array $input): Ticket
    {
        return $this->mutate($project, $actor, $ticket, $input, function (Ticket $ticket, Project $project) use ($actor, $input) {
            ProjectAccess::authorize($actor, $project, 'tickets.verify');
            $this->editable($ticket);
            $this->require(! $ticket->is_blocked, 'signoff', 'Resume this ticket before verification.');
            $data = Validator::make($input, [
                'kind' => ['required', Rule::in(['review', 'qa', 'staging', 'resolution'])],
                'result' => ['required', Rule::in(['pass', 'fail'])], 'note' => 'nullable|string|max:20000',
            ])->validate();
            $this->require(in_array($ticket->stage->type, ['review', 'verification', 'pre_close'], true), 'signoff', 'Sign-offs can only be recorded at review or verification checkpoints.');
            $this->require($data['kind'] !== 'qa' || $ticket->stage->type === 'verification', 'kind', 'QA verification must occur at a verification stage.');
            $this->require($data['result'] !== 'fail' || filled($data['note'] ?? null), 'note', 'Describe the verification failure.');
            $this->ready($ticket, $project);
            $signoff = $ticket->signoffs()->create([
                ...$data, 'actor_id' => $actor->id, 'stage_id' => $ticket->stage_id, 'verified_revision' => $ticket->revision_number,
                'stage_visit' => $ticket->stage_visit, 'verification_cycle' => $ticket->verification_cycle, 'created_at' => now(),
            ]);
            $this->activity($ticket, $actor, 'verified', ['kind' => $data['kind'], 'result' => $data['result'], 'note' => $data['note'] ?? null, 'revision' => $ticket->revision_number, 'signoff_id' => $signoff->id]);
        });
    }

    /** @param array<string, mixed> $input */
    public function action(Project $project, User $actor, Ticket $ticket, array $input): Ticket
    {
        return $this->mutate($project, $actor, $ticket, $input, function (Ticket $ticket, Project $project) use ($actor, $input) {
            ProjectAccess::authorize($actor, $project, 'tickets.transition');
            $data = Validator::make($input, ['action' => ['required', Rule::in(['block', 'resume', 'cancel', 'reopen'])], 'reason' => 'required|string|max:10000'])->validate();
            $action = $data['action'];
            if ($action === 'reopen') {
                ProjectAccess::authorize($actor, $project, 'tickets.close');
                $this->require($ticket->closed_at !== null || $ticket->is_cancelled, 'action', 'Only closed or cancelled tickets can be reopened.');
                $initial = $ticket->workflow->stages()->where('is_initial', true)->firstOrFail();
                $ticket->stage_id = $initial->id;
                $ticket->stage_visit++;
                $ticket->verification_cycle++;
                $ticket->closed_at = null;
                $ticket->closed_by = null;
                $ticket->completion_notes = null;
                $ticket->documentation_impact = null;
                $ticket->documentation_impact_note = null;
                $ticket->is_cancelled = false;
                $ticket->is_blocked = false;
                $ticket->blocked_from_stage_id = null;
                $this->activity($ticket, $actor, 'reopened', ['reason' => $data['reason'], 'to_stage_id' => $initial->id, 'to_visit' => $ticket->stage_visit]);

                return;
            }
            $this->editable($ticket);
            if ($action === 'block') {
                $this->require(! $ticket->is_blocked, 'action', 'Ticket is already blocked.');
                $ticket->is_blocked = true;
                $ticket->blocked_from_stage_id = $ticket->stage_id;
            } elseif ($action === 'resume') {
                $this->require($ticket->is_blocked, 'action', 'This ticket is not blocked.');
                $ticket->is_blocked = false;
                $ticket->blocked_from_stage_id = null;
            } else {
                ProjectAccess::authorize($actor, $project, 'tickets.close');
                $this->require(! $ticket->is_intake_stub, 'action', 'Complete urgent intake before cancelling it.');
                $ticket->is_cancelled = true;
                $ticket->is_blocked = false;
                $ticket->blocked_from_stage_id = null;
                $ticket->verification_cycle++;
            }
            $this->activity($ticket, $actor, ['block' => 'blocked', 'resume' => 'resumed', 'cancel' => 'cancelled'][$action], ['reason' => $data['reason'], 'stage_id' => $ticket->stage_id]);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  callable(Ticket, Project): void  $operation
     */
    private function mutate(Project $project, User $actor, Ticket $ticket, array $input, callable $operation): Ticket
    {
        return DB::transaction(function () use ($project, $actor, $ticket, $input, $operation) {
            $project = Project::lockForUpdate()->findOrFail($project->id);
            ProjectAccess::authorize($actor, $project, 'tickets.view', false);
            $ticket = $project->tickets()->lockForUpdate()->findOrFail($ticket->id);
            $data = Validator::make($input, ['lock_version' => 'required|integer|min:1'])->validate();
            $this->require((int) $data['lock_version'] === $ticket->lock_version, 'lock_version', 'This ticket changed while you were editing. Reload and try again.');
            $operation($ticket, $project);
            $ticket->lock_version++;
            $ticket->save();

            return $ticket;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validateFields(Project $project, array $input, bool $creating): array
    {
        $stub = $creating ? filter_var($input['is_intake_stub'] ?? false, FILTER_VALIDATE_BOOL) : (bool) ($input['is_intake_stub'] ?? false);
        $data = Validator::make($input, [
            'title' => [$stub ? 'nullable' : 'required', 'string', 'max:255'], 'description' => [$stub ? 'nullable' : 'required', 'string', 'max:100000'],
            'category' => ['required', Rule::in(Ticket::CATEGORIES)], 'priority' => ['required', Rule::in(Ticket::PRIORITIES)],
            'module_id' => ['nullable', 'integer', Rule::exists('modules', 'id')->where('project_id', $project->id)],
            'assigned_to' => 'nullable|integer', 'estimate_minutes' => 'nullable|integer|min:0|max:4294967295', 'due_date' => 'nullable|date_format:Y-m-d',
            'acceptance_criteria' => 'sometimes|array|max:100', 'acceptance_criteria.*' => 'required|string|max:2000',
            'label_ids' => 'sometimes|array|max:50', 'label_ids.*' => ['integer', 'distinct', Rule::exists('project_labels', 'id')->where('project_id', $project->id)],
        ])->validate();
        $data['title'] = filled($data['title'] ?? null) ? trim($data['title']) : null;
        $data['description'] = filled($data['description'] ?? null) ? trim($data['description']) : null;
        $this->require(! $stub || ($data['category'] === 'support' && $data['priority'] === 'urgent') || ! $creating, 'is_intake_stub', 'Only urgent support intake may omit required details.');
        $data['acceptance_criteria'] ??= [];

        return $data;
    }

    private function eligibleAssignee(Project $project, int $userId): void
    {
        $member = $project->members()->where('user_id', $userId)->where('is_active', true)->with('user')->first();
        $this->require($member && $member->user->is_active && $member->user->hasProjectPermission($project, 'tickets.work'), 'assigned_to', 'Select an active project member permitted to work on tickets.');
    }

    private function ready(Ticket $ticket, Project $project): void
    {
        $this->require(! $ticket->is_intake_stub && filled($ticket->title) && filled($ticket->description), 'title', 'Complete the ticket title and description before advancing.');
        if (in_array($ticket->category, Ticket::DEVELOPMENT_CATEGORIES, true)) {
            $this->require(count($ticket->acceptance_criteria ?? []) > 0, 'acceptance_criteria', 'Add acceptance criteria before work becomes ready.');
        }
        $this->require($ticket->assigned_to !== null, 'assigned_to', 'Assign an eligible project member before work becomes ready.');
        $this->eligibleAssignee($project, $ticket->assigned_to);
    }

    /** @param array{completion_notes?: string|null, documentation_impact?: string|null, documentation_impact_note?: string|null, ...} $data */
    private function closure(Ticket $ticket, array $data): void
    {
        $this->require(filled($data['completion_notes'] ?? null), 'completion_notes', 'Completion notes are required for closure.');
        $visits = [$ticket->stage_id => $ticket->stage_visit];
        $invalidatedVisits = [];
        foreach ($ticket->activities()->orderBy('id')->get() as $activity) {
            $payload = $activity->payload;
            if (isset($payload['to_stage_id'], $payload['to_visit'])) {
                $visits[$payload['to_stage_id']] = $payload['to_visit'];
            }
            foreach ($payload['invalidated_stage_ids'] ?? [] as $stageId) {
                $invalidatedVisits[$stageId] = $payload['to_visit'];
            }
        }
        $visits[$ticket->stage_id] = $ticket->stage_visit;
        $signoffs = $ticket->signoffs()->where('verified_revision', $ticket->revision_number)->where('verification_cycle', $ticket->verification_cycle)
            ->latest('id')->get()->filter(fn ($signoff) => ($visits[$signoff->stage_id] ?? null) === $signoff->stage_visit && $signoff->stage_visit > ($invalidatedVisits[$signoff->stage_id] ?? 0));
        if (in_array($ticket->category, Ticket::DEVELOPMENT_CATEGORIES, true)) {
            $this->require($signoffs->where('kind', 'qa')->first()?->result === 'pass', 'signoff', 'A current manual QA pass is required for this ticket category.');
        }
        foreach ($ticket->workflow->transitions()->where('requires_signoff', true)->pluck('from_stage_id')->unique() as $stageId) {
            if (isset($visits[$stageId])) {
                $this->require($signoffs->where('stage_id', $stageId)->first()?->result === 'pass', 'signoff', 'A required checkpoint sign-off is missing or outdated.');
            }
        }
        if (in_array($ticket->category, ['new_feature', 'enhancement'], true)) {
            $this->require(in_array($data['documentation_impact'] ?? null, ['required', 'not_required'], true), 'documentation_impact', 'Record whether documentation changes are required.');
            $this->require($data['documentation_impact'] !== 'not_required' || filled($data['documentation_impact_note'] ?? null), 'documentation_impact_note', 'Explain why documentation changes are not required.');
        }
    }

    private function editable(Ticket $ticket): void
    {
        $this->require($ticket->closed_at === null && ! $ticket->is_cancelled, 'ticket', 'Reopen this ticket before making further changes.');
    }

    /** @param array<string, mixed> $payload */
    public function activity(Ticket $ticket, User $actor, string $type, array $payload): void
    {
        $ticket->activities()->create(['actor_id' => $actor->id, 'activity_type' => $type, 'payload' => $payload, 'created_at' => now()]);
    }

    /** @phpstan-assert true $condition */
    private function require(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
