<?php

use App\Models\AuditLog;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectLabel;
use App\Models\ProjectMember;
use App\Models\ProjectMemberPermissionOverride;
use App\Models\ProjectWorkflow;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\TicketStageSignoff;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use Database\Seeders\AstreiaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('the demo seeder creates a complete, idempotent Phase 1 workspace', function () {
    $this->seed(AstreiaDemoSeeder::class);
    $tables = [
        'users', 'permissions', 'projects', 'modules', 'project_members', 'roles',
        'role_permissions', 'project_member_roles', 'project_member_permission_overrides',
        'project_workflows', 'workflow_stages', 'workflow_transitions',
        'project_ticket_counters', 'tickets', 'ticket_stage_signoffs', 'ticket_comments',
        'ticket_activities', 'ticket_attachments', 'project_labels', 'ticket_labels', 'audit_logs',
    ];
    $counts = collect($tables)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()]);

    $this->seed(AstreiaDemoSeeder::class);

    expect(Project::query()->whereIn('code', ['AST', 'OPS'])->count())->toBe(2);
    expect(Project::where('code', 'AST')->firstOrFail()->current_workflow_id)->not->toBeNull();
    expect(DB::table('ticket_stage_signoffs')
        ->join('workflow_stages', 'workflow_stages.id', '=', 'ticket_stage_signoffs.stage_id')
        ->where('workflow_stages.type', 'verification')
        ->count())->toBe(DB::table('ticket_stage_signoffs')->count());
    foreach ($tables as $table) {
        expect(DB::table($table)->count())->toBe($counts[$table])->toBeGreaterThan(0, 'Expected '.$table.' to contain demo data.');
    }

    $this->assertDatabaseHas('users', ['email' => 'admin@astreia.test', 'is_system_admin' => true]);
    $this->assertDatabaseHas('tickets', ['project_id' => Project::where('code', 'AST')->value('id'), 'number' => 1]);
    $this->assertDatabaseHas('ticket_attachments', ['storage_disk' => 'private']);
});

test('ticket and transition factories create records on a published, usable workflow graph', function () {
    $ticket = Ticket::factory()->create();
    $workflow = $ticket->workflow()->firstOrFail();
    $transition = WorkflowTransition::factory()->create();

    expect($workflow->state)->toBe('published');
    expect($ticket->project->current_workflow_id)->toBe($workflow->id);
    expect($workflow->stages()->where('is_initial', true)->where('type', 'intake')->count())->toBe(1);
    expect($workflow->stages()->where('type', 'verification')->exists())->toBeTrue();
    expect($workflow->stages()->where('type', 'closed')->exists())->toBeTrue();
    expect($ticket->stage()->firstOrFail()->workflow_id)->toBe($workflow->id);
    expect($transition->fromStage()->firstOrFail()->workflow_id)->toBe($transition->workflow_id);
    expect($transition->toStage()->firstOrFail()->workflow_id)->toBe($transition->workflow_id);
});

test('every Phase 1 model factory creates a record with valid relationships', function () {
    foreach ([
        AuditLog::class, Module::class, Permission::class, Project::class, ProjectLabel::class,
        ProjectMember::class, ProjectMemberPermissionOverride::class, ProjectWorkflow::class,
        Role::class, Ticket::class, TicketActivity::class, TicketAttachment::class,
        TicketComment::class, TicketStageSignoff::class, WorkflowStage::class, WorkflowTransition::class,
    ] as $model) {
        expect($model::factory()->create()->exists)->toBeTrue();
    }
});
