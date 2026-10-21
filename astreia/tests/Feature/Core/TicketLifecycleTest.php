<?php

use App\Models\Permission;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_system_admin' => true]);
    legacyProjectFixture($this->admin);
    $this->project = Project::where('code', 'HRIS')->firstOrFail();
    $this->path = '/projects/'.$this->project->id.'/tickets';
    $this->worker = User::factory()->create();
    $member = $this->project->members()->create(['user_id' => $this->worker->id]);
    $member->roles()->attach($this->project->roles()->where('name', 'Developer')->firstOrFail()->id, ['project_id' => $this->project->id]);
});

function ticketInput(array $extra = []): array
{
    return [...['title' => 'Correct leave balance', 'description' => 'Balance must include approved leave.', 'category' => 'bug_fix', 'priority' => 'normal', 'acceptance_criteria' => ['Approved leave changes the balance.']], ...$extra];
}

function advanceCore(Ticket $ticket, string $key, array $extra = [])
{
    $ticket->refresh();
    $edge = $ticket->workflow->transitions()->where('from_stage_id', $ticket->stage_id)->whereHas('toStage', fn ($q) => $q->where('key', $key))->firstOrFail();

    return test()->post('/projects/'.$ticket->project_id.'/tickets/'.$ticket->id.'/transitions', [...['lock_version' => $ticket->lock_version, 'transition_id' => $edge->id], ...$extra]);
}

test('normal and urgent intake allocate permanent sequential keys without inventing missing details', function () {
    $this->post($this->path, ticketInput())->assertRedirect();
    $this->post($this->path, ['category' => 'support', 'priority' => 'urgent', 'is_intake_stub' => true])->assertRedirect();
    $tickets = $this->project->tickets()->orderBy('number')->get();
    expect($tickets->pluck('key')->all())->toBe(['HRIS-00001', 'HRIS-00002']);
    expect($tickets[1]->title)->toBeNull()->and($tickets[1]->description)->toBeNull()->and($tickets[1]->is_intake_stub)->toBeTrue();
    advanceCore($tickets[1], 'ready')->assertSessionHasErrors();
    expect($tickets[1]->fresh()->stage->type)->toBe('intake');
});

test('readiness requires acceptance criteria and an eligible active member', function () {
    $this->post($this->path, ticketInput(['acceptance_criteria' => []]))->assertRedirect();
    $ticket = Ticket::firstOrFail();
    advanceCore($ticket, 'ready')->assertSessionHasErrors();
    $this->patch($this->path.'/'.$ticket->id, [...ticketInput(), 'lock_version' => $ticket->lock_version, 'assigned_to' => $this->worker->id])->assertRedirect();
    advanceCore($ticket, 'ready')->assertRedirect();
    $outsider = User::factory()->create();
    $this->patch($this->path.'/'.$ticket->id, [...ticketInput(), 'lock_version' => $ticket->fresh()->lock_version, 'assigned_to' => $outsider->id])->assertSessionHasErrors('assigned_to');
});

test('crafted stage jumps and cross-project reads are rejected', function () {
    $this->post($this->path, ticketInput(['assigned_to' => $this->worker->id]));
    $ticket = Ticket::firstOrFail();
    $closedEdge = $ticket->workflow->transitions()->whereHas('toStage', fn ($q) => $q->where('type', 'closed'))->firstOrFail();
    $this->post($this->path.'/'.$ticket->id.'/transitions', ['lock_version' => 1, 'transition_id' => $closedEdge->id])->assertSessionHasErrors();
    $this->actingAs(User::factory()->create())->get($this->path.'/'.$ticket->id)->assertForbidden();
    $this->post($this->path.'/'.$ticket->id.'/comments', ['body' => 'Unauthorized'])->assertForbidden();
    $this->assertDatabaseCount('ticket_comments', 0);
});

test('verification and closure require current QA pass and completion notes', function () {
    $this->post($this->path, ticketInput(['assigned_to' => $this->worker->id]));
    $ticket = Ticket::firstOrFail();
    advanceCore($ticket, 'ready')->assertRedirect();
    advanceCore($ticket, 'development')->assertRedirect();
    advanceCore($ticket, 'qa')->assertRedirect();
    advanceCore($ticket, 'closed', ['completion_notes' => 'Fixed balance calculation'])->assertSessionHasErrors();
    $this->post($this->path.'/'.$ticket->id.'/signoffs', ['lock_version' => $ticket->fresh()->lock_version, 'kind' => 'qa', 'result' => 'pass', 'note' => 'Verified approved leave'])->assertRedirect();
    advanceCore($ticket, 'closed')->assertSessionHasErrors();
    advanceCore($ticket, 'closed', ['completion_notes' => 'Fixed balance calculation'])->assertRedirect();
    expect($ticket->fresh()->stage->type)->toBe('closed');
    expect($ticket->fresh()->closed_by)->toBe($this->admin->id);
});

test('material edits and returning to QA invalidate previous signoffs', function () {
    $this->post($this->path, ticketInput(['assigned_to' => $this->worker->id]));
    $ticket = Ticket::firstOrFail();
    advanceCore($ticket, 'ready');
    advanceCore($ticket, 'development');
    advanceCore($ticket, 'qa');
    $this->post($this->path.'/'.$ticket->id.'/signoffs', ['lock_version' => $ticket->fresh()->lock_version, 'kind' => 'qa', 'result' => 'pass']);
    $this->patch($this->path.'/'.$ticket->id, [...ticketInput(['description' => 'Changed requirements']), 'lock_version' => $ticket->fresh()->lock_version, 'assigned_to' => $this->worker->id])->assertRedirect();
    advanceCore($ticket, 'closed', ['completion_notes' => 'Done'])->assertSessionHasErrors();
    $this->post($this->path.'/'.$ticket->id.'/signoffs', ['lock_version' => $ticket->fresh()->lock_version, 'kind' => 'qa', 'result' => 'fail', 'note' => 'Balance still wrong'])->assertRedirect();
    advanceCore($ticket, 'development', ['reason' => 'Fix failed QA'])->assertRedirect();
    advanceCore($ticket, 'qa')->assertRedirect();
    advanceCore($ticket, 'closed', ['completion_notes' => 'Done'])->assertSessionHasErrors();
    expect($ticket->fresh()->revision_number)->toBe(2);
});

test('feature closure records pending documentation and reopen preserves key but invalidates approvals', function () {
    $this->post($this->path, ticketInput(['category' => 'enhancement', 'assigned_to' => $this->worker->id]));
    $ticket = Ticket::firstOrFail();
    advanceCore($ticket, 'ready');
    advanceCore($ticket, 'development');
    advanceCore($ticket, 'qa');
    $this->post($this->path.'/'.$ticket->id.'/signoffs', ['lock_version' => $ticket->fresh()->lock_version, 'kind' => 'qa', 'result' => 'pass']);
    advanceCore($ticket, 'closed', ['completion_notes' => 'Done'])->assertSessionHasErrors();
    advanceCore($ticket, 'closed', ['completion_notes' => 'Done', 'documentation_impact' => 'not_required'])->assertSessionHasErrors();
    advanceCore($ticket, 'closed', ['completion_notes' => 'Done', 'documentation_impact' => 'required'])->assertRedirect();
    $this->post($this->path.'/'.$ticket->id.'/actions', ['lock_version' => $ticket->fresh()->lock_version, 'action' => 'reopen', 'reason' => 'Regression found'])->assertRedirect();
    expect($ticket->fresh()->key)->toBe('HRIS-00001')->and($ticket->fresh()->closed_at)->toBeNull();
    expect($ticket->activities()->where('activity_type', 'reopened')->exists())->toBeTrue();
});

test('optimistic locking rejects stale edits and blocked tickets preserve their resume stage', function () {
    $this->post($this->path, ticketInput(['assigned_to' => $this->worker->id]));
    $ticket = Ticket::firstOrFail();
    advanceCore($ticket, 'ready');
    $this->patch($this->path.'/'.$ticket->id, [...ticketInput(), 'lock_version' => 1])->assertSessionHasErrors('lock_version');
    $this->post($this->path.'/'.$ticket->id.'/actions', ['lock_version' => $ticket->fresh()->lock_version, 'action' => 'block', 'reason' => 'Waiting for client'])->assertRedirect();
    advanceCore($ticket, 'development')->assertSessionHasErrors();
    $this->post($this->path.'/'.$ticket->id.'/actions', ['lock_version' => $ticket->fresh()->lock_version, 'action' => 'resume', 'reason' => 'Client replied'])->assertRedirect();
    advanceCore($ticket, 'development')->assertRedirect();
});

test('tickets retain their assigned workflow when a new version is published', function () {
    $this->post($this->path, ticketInput());
    $ticket = Ticket::firstOrFail();
    $oldId = $ticket->workflow_id;
    $service = app(WorkflowService::class);
    $draft = $service->saveDraft($this->project, $this->admin, WorkflowService::template());
    $service->publish($this->project, $this->admin, $draft);
    $this->post($this->path, ticketInput());
    expect($ticket->fresh()->workflow_id)->toBe($oldId);
    expect(Ticket::latest('id')->first()->workflow_id)->toBe($draft->id);
});

test('configured shortcut transitions cannot bypass QA closure checks', function () {
    $graph = WorkflowService::template();
    $graph['transitions'][] = ['from_key' => 'ready', 'to_key' => 'closed', 'required_permission_key' => null, 'requires_reason' => false, 'requires_signoff' => false];
    $service = app(WorkflowService::class);
    $draft = $service->saveDraft($this->project, $this->admin, $graph);
    $service->publish($this->project, $this->admin, $draft);
    $this->post($this->path, ticketInput(['assigned_to' => $this->worker->id]));
    $ticket = Ticket::firstOrFail();
    advanceCore($ticket, 'ready')->assertRedirect();
    advanceCore($ticket, 'closed', ['completion_notes' => 'Skipped QA'])->assertSessionHasErrors('signoff');
    expect($ticket->fresh()->closed_at)->toBeNull();
});

test('developers can transition their assigned work but cannot edit requirements or close tickets', function () {
    $this->post($this->path, ticketInput(['assigned_to' => $this->worker->id]));
    $ticket = Ticket::firstOrFail();
    advanceCore($ticket, 'ready');
    advanceCore($ticket, 'development');
    $this->actingAs($this->worker);
    $this->patch($this->path.'/'.$ticket->id, [...ticketInput(['title' => 'Changed']), 'lock_version' => $ticket->fresh()->lock_version])->assertForbidden();
    advanceCore($ticket, 'qa')->assertRedirect();
    $this->post($this->path.'/'.$ticket->id.'/signoffs', ['lock_version' => $ticket->fresh()->lock_version, 'kind' => 'qa', 'result' => 'pass'])->assertForbidden();
    advanceCore($ticket, 'closed', ['completion_notes' => 'Not authorized'])->assertForbidden();
});

test('due dates and zero estimates survive assignment-only changes', function () {
    $this->post($this->path, ticketInput(['assigned_to' => $this->worker->id, 'due_date' => '2026-12-01', 'estimate_minutes' => 0]));
    $ticket = Ticket::firstOrFail();
    $this->patch($this->path.'/'.$ticket->id, ['lock_version' => $ticket->lock_version, 'assigned_to' => $this->worker->id])->assertRedirect();
    expect($ticket->fresh()->estimate_minutes)->toBe(0)->and($ticket->fresh()->due_date->format('Y-m-d'))->toBe('2026-12-01');
});

test('an assignment manager without edit permission can reassign a ticket with a due date', function () {
    $this->post($this->path, ticketInput(['assigned_to' => $this->worker->id, 'due_date' => '2026-12-01']));
    $ticket = Ticket::firstOrFail();
    $next = User::factory()->create();
    $nextMember = $this->project->members()->create(['user_id' => $next->id]);
    $nextMember->roles()->attach($this->project->roles()->where('name', 'Developer')->firstOrFail()->id, ['project_id' => $this->project->id]);
    $manager = User::factory()->create();
    $role = $this->project->roles()->create(['name' => 'Assigner only']);
    $role->permissions()->sync(Permission::whereIn('key', ['project.view', 'tickets.view', 'tickets.assign'])->pluck('id'));
    $member = $this->project->members()->create(['user_id' => $manager->id]);
    $member->roles()->attach($role->id, ['project_id' => $this->project->id]);
    $this->actingAs($manager)->patch($this->path.'/'.$ticket->id, ['lock_version' => $ticket->lock_version, 'assigned_to' => $next->id])->assertRedirect();
    expect($ticket->fresh()->assigned_to)->toBe($next->id);
});

test('returning to development invalidates QA even when a closure shortcut exists', function () {
    $graph = WorkflowService::template();
    $graph['transitions'][] = ['from_key' => 'development', 'to_key' => 'closed', 'required_permission_key' => null, 'requires_reason' => false, 'requires_signoff' => false];
    $service = app(WorkflowService::class);
    $draft = $service->saveDraft($this->project, $this->admin, $graph);
    $service->publish($this->project, $this->admin, $draft);
    $this->post($this->path, ticketInput(['assigned_to' => $this->worker->id]));
    $ticket = Ticket::firstOrFail();
    advanceCore($ticket, 'ready');
    advanceCore($ticket, 'development');
    advanceCore($ticket, 'qa');
    $this->post($this->path.'/'.$ticket->id.'/signoffs', ['lock_version' => $ticket->fresh()->lock_version, 'kind' => 'qa', 'result' => 'pass'])->assertRedirect();
    advanceCore($ticket, 'development', ['reason' => 'More implementation work required'])->assertRedirect();
    advanceCore($ticket, 'closed', ['completion_notes' => 'Reworked without another QA'])->assertSessionHasErrors('signoff');
    expect($ticket->fresh()->closed_at)->toBeNull();
});
