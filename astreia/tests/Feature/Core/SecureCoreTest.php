<?php

use App\Models\Permission;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_system_admin' => true]);
    $this->actingAs($this->admin)->post('/projects', ['code' => 'CORE', 'name' => 'Core workspace', 'timezone' => 'Asia/Singapore'])->assertRedirect();
    $this->project = Project::where('code', 'CORE')->firstOrFail();
    $this->path = '/projects/'.$this->project->id;
});

function secureInput(Project $project, array $extra = []): array
{
    return [...[
        'title' => 'Fix customer lookup', 'description' => 'Find a customer by their reference.',
        'work_type_id' => $project->workTypes()->where('code', 'bug')->value('id'),
        'priority_id' => $project->priorities()->where('code', 'normal')->value('id'),
        'acceptance_criteria' => [],
    ], ...$extra];
}

function secureAdvance(Ticket $ticket, string $key, array $extra = [])
{
    $ticket->refresh();
    $edge = $ticket->workflow->transitions()->where('from_stage_id', $ticket->stage_id)->whereHas('toStage', fn ($q) => $q->where('key', $key))->firstOrFail();

    return test()->post('/projects/'.$ticket->project_id.'/tickets/'.$ticket->id.'/transitions', ['lock_version' => $ticket->lock_version, 'transition_id' => $edge->id, ...$extra]);
}

test('new project has core catalogs and a usable seeded graph without changing legacy projects', function () {
    expect($this->project->process_model)->toBe('core');
    expect($this->project->timezone)->toBe('Asia/Singapore');
    expect($this->project->workflows()->first()->stages()->orderBy('sort_order')->pluck('name')->all())->toBe(['Open', 'In progress', 'Resolved']);
    expect($this->project->workTypes()->orderBy('id')->pluck('code')->all())->toBe(['task', 'bug', 'story']);
    $legacy = Project::factory()->create();
    expect($legacy->fresh()->process_model)->toBe('legacy');
    $this->get($this->path.'/workflows')->assertForbidden();
});

test('core lifecycle requires assignment and outcome and retains past resolution on reopen', function () {
    $this->post($this->path.'/tickets', secureInput($this->project, ['estimate_minutes' => 0]))->assertSessionHasNoErrors();
    $ticket = $this->project->tickets()->firstOrFail();
    secureAdvance($ticket, 'in_progress')->assertSessionHasErrors('assigned_to');
    $this->patch($this->path.'/tickets/'.$ticket->id, ['lock_version' => 1, 'assigned_to' => $this->admin->id])->assertSessionHasNoErrors();
    secureAdvance($ticket, 'in_progress')->assertSessionHasNoErrors();
    secureAdvance($ticket, 'resolved', ['completion_notes' => 'Fixed'])->assertSessionHasErrors('resolution_id');
    $resolution = $this->project->resolutions()->where('code', 'duplicate')->firstOrFail();
    secureAdvance($ticket, 'resolved', ['resolution_id' => $resolution->id])->assertSessionHasErrors('completion_notes');
    secureAdvance($ticket, 'resolved', ['resolution_id' => $resolution->id, 'completion_notes' => 'Covered by CORE-00002'])->assertSessionHasNoErrors();
    expect($ticket->fresh()->resolution_id)->toBe($resolution->id)->and($ticket->fresh()->closed_at)->not->toBeNull();
    $this->post($this->path.'/tickets/'.$ticket->id.'/actions', ['lock_version' => $ticket->fresh()->lock_version, 'action' => 'reopen', 'reason' => 'Separate defect'])->assertSessionHasNoErrors();
    expect($ticket->fresh()->key)->toBe('CORE-00001')->and($ticket->fresh()->stage->key)->toBe('open')->and($ticket->fresh()->resolution_id)->toBeNull()->and($ticket->fresh()->closed_at)->toBeNull()->and($ticket->fresh()->estimate_minutes)->toBe(0);
    expect($ticket->activities()->where('activity_type', 'transitioned')->get()->contains(fn ($event) => ($event->payload['resolution_id'] ?? null) === $resolution->id))->toBeTrue();
});

test('core rejects incomplete intake, foreign catalogs and protected edits', function () {
    $this->post($this->path.'/tickets', secureInput($this->project, ['title' => '', 'is_intake_stub' => true]))->assertSessionHasErrors('title');
    $this->post('/projects', ['code' => 'OTHER', 'name' => 'Other']);
    $other = Project::where('code', 'OTHER')->firstOrFail();
    $this->post($this->path.'/tickets', secureInput($this->project, ['work_type_id' => $other->workTypes()->value('id')]))->assertSessionHasErrors('work_type_id');
    $this->post($this->path.'/tickets', secureInput($this->project))->assertSessionHasNoErrors();
    $ticket = $this->project->tickets()->firstOrFail();
    $this->patch($this->path.'/tickets/'.$ticket->id, ['lock_version' => 1, 'stage_id' => $ticket->workflow->stages()->where('key', 'resolved')->value('id')])->assertSessionHasErrors('stage_id');
    $this->patch($this->path.'/tickets/'.$ticket->id, ['lock_version' => 1, 'title' => 'Newer summary'])->assertSessionHasNoErrors();
    $this->patch($this->path.'/tickets/'.$ticket->id, ['lock_version' => 1, 'title' => 'Stale summary'])->assertSessionHasErrors('lock_version');
    expect($ticket->fresh()->title)->toBe('Newer summary');
});

test('core filtered totals use project timezone and stable sorting', function () {
    $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 10, 21)->setTime(18, 0));
    $this->post($this->path.'/tickets', secureInput($this->project, ['title' => 'Same', 'due_date' => '2026-10-21']));
    $this->post($this->path.'/tickets', secureInput($this->project, ['title' => 'Same', 'due_date' => '2026-10-23']));
    $this->get($this->path.'?due=overdue&sort=title&direction=asc')->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('counts.open', 1)->where('counts.overdue', 1));
    $this->get($this->path.'?sort=title&direction=asc')->assertInertia(fn (Assert $page) => $page->where('tickets.data.0.number', 1)->where('tickets.data.1.number', 2));
    $this->get($this->path.'?q=CORE-00002')->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('counts.open', 1));
    $this->travelBack();
});

test('archiving core requires reason and blocks admin domain writes until restoration', function () {
    $this->post($this->path.'/tickets', secureInput($this->project));
    $ticket = $this->project->tickets()->firstOrFail();
    $data = ['name' => $this->project->name, 'status' => 'archived', 'timezone' => 'Asia/Singapore', 'lock_version' => 1];
    $this->patch($this->path, $data)->assertSessionHasErrors('reason');
    $this->patch($this->path, [...$data, 'reason' => 'Project complete'])->assertSessionHasNoErrors();
    $this->get('/projects')->assertInertia(fn (Assert $page) => $page->has('projects.data', 0));
    $this->get('/projects?status=archived')->assertInertia(fn (Assert $page) => $page->has('projects.data', 1));
    $this->get($this->path.'/tickets/'.$ticket->id)->assertOk();
    $this->patch($this->path.'/tickets/'.$ticket->id, ['lock_version' => 1, 'title' => 'Forbidden'])->assertForbidden();
    $this->patch($this->path, [...$data, 'status' => 'active', 'lock_version' => 2, 'reason' => 'New work'])->assertSessionHasNoErrors();
    $this->patch($this->path.'/tickets/'.$ticket->id, ['lock_version' => 1, 'title' => 'Allowed'])->assertSessionHasNoErrors();
});

test('core deferred features cannot be enabled through direct endpoints', function () {
    $this->post($this->path.'/tickets', secureInput($this->project));
    $ticket = $this->project->tickets()->firstOrFail();
    $this->post($this->path.'/tickets/'.$ticket->id.'/comments', ['body' => 'Deferred'])->assertForbidden();
    $this->post($this->path.'/labels', ['name' => 'Deferred'])->assertForbidden();
    $this->post($this->path.'/tickets/'.$ticket->id.'/signoffs', ['lock_version' => 1, 'kind' => 'qa', 'result' => 'pass'])->assertForbidden();
    $this->assertDatabaseCount('ticket_comments', 0);
});

test('inactive workers are flagged and cannot start active work', function () {
    $worker = User::factory()->create();
    $member = $this->project->members()->create(['user_id' => $worker->id]);
    $role = $this->project->roles()->create(['name' => 'Worker']);
    $role->permissions()->sync(Permission::whereIn('key', ['project.view', 'tickets.view', 'tickets.work'])->pluck('id'));
    $member->roles()->attach($role->id, ['project_id' => $this->project->id]);
    $this->post($this->path.'/tickets', secureInput($this->project, ['assigned_to' => $worker->id]));
    $ticket = $this->project->tickets()->firstOrFail();
    $worker->forceFill(['is_active' => false])->save();
    secureAdvance($ticket, 'in_progress')->assertSessionHasErrors('assigned_to');
    $this->get($this->path.'/tickets/'.$ticket->id)->assertInertia(fn (Assert $page) => $page->where('ticket.needs_reassignment', true));
});

test('core access and filtered counts do not leak to a second project or global administrator', function () {
    $this->post($this->path.'/tickets', secureInput($this->project));
    $outsider = User::factory()->create(['is_system_admin' => true]);
    $this->actingAs($outsider)->get($this->path.'?q=CORE-00001')->assertForbidden();
    $this->get('/projects')->assertInertia(fn (Assert $page) => $page->has('projects.data', 0));
    $this->get($this->path.'/tickets/'.$this->project->tickets()->value('id'))->assertForbidden();
});

test('key searches are exact and summary wildcards are treated literally', function () {
    $this->post($this->path.'/tickets', secureInput($this->project, ['title' => '100%_coverage']));
    $this->post($this->path.'/tickets', secureInput($this->project, ['title' => 'Review CORE-00001', 'description' => '100 percent coverage']));
    $this->get($this->path.'?q=CORE-00001')->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.number', 1));
    $this->get($this->path.'?q='.urlencode('%_'))->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1)->where('tickets.data.0.number', 1));
});

test('failed intake does not create partial tickets and successful numbers remain unique', function () {
    $this->post($this->path.'/tickets', secureInput($this->project, ['description' => '']))->assertSessionHasErrors('description');
    $this->assertDatabaseCount('tickets', 0);
    $this->assertDatabaseCount('ticket_activities', 0);
    $this->post($this->path.'/tickets', secureInput($this->project))->assertSessionHasNoErrors();
    $this->post($this->path.'/tickets', secureInput($this->project))->assertSessionHasNoErrors();
    expect($this->project->tickets()->orderBy('number')->pluck('number')->all())->toBe([1, 2]);
    $this->assertDatabaseHas('project_ticket_counters', ['project_id' => $this->project->id, 'next_number' => 3]);
});
