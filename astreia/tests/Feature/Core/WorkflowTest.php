<?php

use App\Models\Project;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('projects start with a usable published template and new publications retain old versions', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $this->actingAs($admin)->post('/projects', ['code' => 'HRIS', 'name' => 'HRIS'])->assertRedirect();
    $project = Project::where('code', 'HRIS')->firstOrFail();
    expect($project->current_workflow_id)->not->toBeNull();
    $original = $project->workflows()->where('id', $project->current_workflow_id)->firstOrFail();
    $graph = WorkflowService::template();
    $graph['stages'][2]['name'] = 'Development 2';
    $this->post('/projects/'.$project->id.'/workflows/draft', $graph)->assertRedirect();
    $draft = $project->workflows()->where('state', 'draft')->firstOrFail();
    $this->post('/projects/'.$project->id.'/workflows/'.$draft->id.'/publish')->assertRedirect();
    expect($project->fresh()->current_workflow_id)->toBe($draft->id);
    expect($original->fresh()->stages()->where('key', 'development')->firstOrFail()->name)->toBe('Development');
    $this->post('/projects/'.$project->id.'/workflows/draft', [...$graph, 'workflow_id' => $original->id])->assertSessionHasErrors();
});

test('invalid graphs cannot publish or change the active workflow', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $this->actingAs($admin)->post('/projects', ['code' => 'HRIS', 'name' => 'HRIS']);
    $project = Project::firstOrFail();
    $originalId = $project->current_workflow_id;
    $graph = WorkflowService::template();
    $graph['stages'][1]['is_initial'] = true;
    $this->post('/projects/'.$project->id.'/workflows/draft', $graph)->assertRedirect();
    $draft = $project->workflows()->where('state', 'draft')->firstOrFail();
    $this->post('/projects/'.$project->id.'/workflows/'.$draft->id.'/publish')->assertSessionHasErrors();
    expect($project->fresh()->current_workflow_id)->toBe($originalId);
});

test('workflow endpoints must belong to the configured version', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $this->actingAs($admin)->post('/projects', ['code' => 'HRIS', 'name' => 'HRIS']);
    $project = Project::firstOrFail();
    $graph = WorkflowService::template();
    $graph['transitions'][0]['to_key'] = 'another_project_stage';
    $this->post('/projects/'.$project->id.'/workflows/draft', $graph)->assertSessionHasErrors();
});

test('publication rejects signoff gates on stages where signoffs cannot be recorded', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $this->actingAs($admin)->post('/projects', ['code' => 'HRIS', 'name' => 'HRIS']);
    $project = Project::firstOrFail();
    $graph = WorkflowService::template();
    $graph['transitions'][2]['requires_signoff'] = true;
    $this->post('/projects/'.$project->id.'/workflows/draft', $graph)->assertRedirect();
    $draft = $project->workflows()->where('state', 'draft')->firstOrFail();
    $this->post('/projects/'.$project->id.'/workflows/'.$draft->id.'/publish')->assertSessionHasErrors('workflow');
});

test('publication requires a reachable verification checkpoint for development tickets', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $this->actingAs($admin)->post('/projects', ['code' => 'HRIS', 'name' => 'HRIS']);
    $project = Project::firstOrFail();
    $graph = WorkflowService::template();
    $graph['stages'][3]['type'] = 'review';
    $this->post('/projects/'.$project->id.'/workflows/draft', $graph)->assertRedirect();
    $draft = $project->workflows()->where('state', 'draft')->firstOrFail();
    $this->post('/projects/'.$project->id.'/workflows/'.$draft->id.'/publish')->assertSessionHasErrors('workflow');
});
