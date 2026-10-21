<?php

use App\Models\Priority;
use App\Models\Project;
use App\Models\Resolution;
use App\Models\User;
use App\Models\WorkType;
use App\Services\TicketService;
use Database\Seeders\CoreDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

test('core demo seeds two isolated projects with usable catalogs and real lifecycle history', function () {
    $this->seed(CoreDemoSeeder::class);

    $projects = Project::where('process_model', 'core')->orderBy('code')->get();
    expect($projects->pluck('code')->all())->toBe(['COREAPP', 'COREOPS'])
        ->and($projects->pluck('created_by')->unique())->toHaveCount(2);

    foreach ($projects as $project) {
        expect($project->workTypes()->orderBy('id')->pluck('code')->all())->toBe(['task', 'bug', 'story'])
            ->and($project->priorities()->count())->toBe(4)
            ->and($project->resolutions()->count())->toBe(4)
            ->and($project->workflows()->count())->toBe(1)
            ->and($project->tickets()->count())->toBe(6);
        $workflow = $project->workflows()->findOrFail($project->current_workflow_id);
        expect($workflow->state)->toBe('published')
            ->and($workflow->stages()->orderBy('sort_order')->pluck('key')->all())->toBe(['open', 'in_progress', 'resolved']);

        $tickets = $project->tickets()->with(['stage', 'resolution', 'activities'])->orderBy('number')->get();
        expect($tickets->pluck('number')->all())->toBe([1, 2, 3, 4, 5, 6])
            ->and($tickets[0]->estimate_minutes)->toBeNull()
            ->and($tickets[0]->assigned_to)->toBeNull()
            ->and($tickets[1]->estimate_minutes)->toBe(0)
            ->and($tickets[2]->stage->key)->toBe('in_progress')
            ->and($tickets[2]->due_date->format('Y-m-d'))->toBeLessThan(now($project->timezone)->toDateString())
            ->and($tickets[3]->resolution->outcome)->toBe('success')
            ->and($tickets[4]->resolution->outcome)->toBe('duplicate')
            ->and($tickets[5]->stage->key)->toBe('open')
            ->and($tickets[5]->resolution_id)->toBeNull()
            ->and($tickets[5]->closed_at)->toBeNull()
            ->and($tickets[5]->activities->pluck('activity_type')->all())->toBe(['created', 'transitioned', 'transitioned', 'reopened']);
        foreach ($tickets as $ticket) {
            expect($project->workTypes()->whereKey($ticket->work_type_id)->exists())->toBeTrue()
                ->and($project->priorities()->whereKey($ticket->priority_id)->exists())->toBeTrue()
                ->and($ticket->is_intake_stub)->toBeFalse()
                ->and($ticket->activities->first()->activity_type)->toBe('created');
        }

        // The demo worker must be able to complete an actual seeded ticket.
        $ticket = $tickets[2];
        $worker = User::findOrFail($ticket->assigned_to);
        $edge = $workflow->transitions()->where('from_stage_id', $ticket->stage_id)->firstOrFail();
        $resolved = app(TicketService::class)->transition($project, $worker, $ticket, [
            'transition_id' => $edge->id, 'lock_version' => $ticket->lock_version,
            'resolution_id' => $project->resolutions()->where('code', 'done')->value('id'),
            'completion_notes' => 'Verified the seeded work through the real workflow.',
        ]);
        expect($resolved->stage->key)->toBe('resolved')->and($resolved->closed_at)->not->toBeNull();
    }
});

test('core demo gives managers scoped access and keeps viewer and suspended accounts restricted', function () {
    $this->seed(CoreDemoSeeder::class);
    $app = Project::where('code', 'COREAPP')->firstOrFail();
    $ops = Project::where('code', 'COREOPS')->firstOrFail();
    $manager = User::findOrFail($app->created_by);
    $otherManager = User::findOrFail($ops->created_by);
    $worker = User::where('email', 'core-worker@astreia.test')->firstOrFail();
    $viewer = User::where('email', 'core-viewer@astreia.test')->firstOrFail();
    $suspended = User::where('email', 'core-suspended@astreia.test')->firstOrFail();
    $admin = User::where('email', 'admin@astreia.test')->firstOrFail();

    expect($manager->hasProjectPermission($app, 'access.manage'))->toBeTrue()
        ->and($manager->hasProjectPermission($ops, 'project.view'))->toBeFalse()
        ->and($otherManager->hasProjectPermission($app, 'project.view'))->toBeFalse()
        ->and($viewer->hasProjectPermission($app, 'tickets.view'))->toBeTrue()
        ->and($viewer->hasProjectPermission($app, 'tickets.create'))->toBeFalse()
        ->and($viewer->hasProjectPermission($app, 'tickets.close'))->toBeFalse()
        ->and($viewer->hasProjectPermission($ops, 'project.view'))->toBeFalse()
        ->and($admin->hasProjectPermission($app, 'project.view'))->toBeFalse()
        ->and($admin->hasProjectPermission($ops, 'tickets.create'))->toBeFalse()
        ->and($suspended->is_active)->toBeFalse()
        ->and($suspended->hasProjectPermission($app, 'tickets.work'))->toBeFalse();
    foreach (['tickets.create', 'tickets.work', 'tickets.transition', 'tickets.close'] as $permission) {
        expect($worker->hasProjectPermission($app, $permission))->toBeTrue();
    }
});

test('core demo reruns preserve edited records credentials permissions and live counters', function () {
    $existing = User::factory()->create(['email' => 'core-manager@astreia.test', 'password' => 'owner-selected-password']);
    $hash = $existing->password;
    $this->seed(CoreDemoSeeder::class);
    expect($existing->fresh()->password)->toBe($hash);

    $project = Project::where('code', 'COREAPP')->firstOrFail();
    $ticket = $project->tickets()->firstOrFail();
    $ticket->update(['title' => 'Edited after demo setup', 'estimate_minutes' => 317]);
    $project->update(['name' => 'Real team workspace', 'status' => 'archived', 'archived_at' => now()]);
    $project->workTypes()->firstOrFail()->update(['name' => 'Team-defined type', 'is_active' => false]);
    $project->roles()->firstOrFail()->permissions()->detach();
    $project->members()->where('user_id', $existing->id)->update(['is_active' => false]);
    $existing->forceFill(['password' => 'new-password', 'is_active' => false])->save();
    DB::table('project_ticket_counters')->where('project_id', $project->id)->update(['next_number' => 100]);
    $before = coreDemoSnapshot();

    $this->seed(CoreDemoSeeder::class);

    expect(coreDemoSnapshot())->toBe($before)
        ->and(Hash::check('new-password', $existing->fresh()->password))->toBeTrue();

    $project->update(['status' => 'active', 'archived_at' => null]);
    $next = app(TicketService::class)->create($project, User::where('email', 'core-worker@astreia.test')->firstOrFail(), [
        'title' => 'New work after a demo rerun', 'description' => 'Keep the live project sequence.',
        'work_type_id' => $project->workTypes()->where('is_active', true)->value('id'),
        'priority_id' => $project->priorities()->value('id'),
    ]);
    expect($next->number)->toBe(100)->and($next->key)->toBe('COREAPP-00100');
});

test('core demo leaves existing project code collisions untouched', function () {
    $existing = Project::factory()->create(['code' => 'COREAPP', 'name' => 'Existing legacy workspace']);
    $before = $existing->fresh()->getAttributes();

    $this->seed(CoreDemoSeeder::class);

    expect($existing->fresh()->getAttributes())->toBe($before)
        ->and($existing->tickets()->count())->toBe(0)
        ->and($existing->roles()->count())->toBe(0)
        ->and($existing->workflows()->count())->toBe(0)
        ->and(Project::where('code', 'COREOPS')->where('process_model', 'core')->exists())->toBeTrue();
});

test('core demo rolls back all seed writes when an existing manager cannot act', function () {
    User::factory()->create(['email' => 'core-ops-manager@astreia.test', 'is_active' => false]);
    $before = coreDemoSnapshot();

    try {
        $this->seed(CoreDemoSeeder::class);
        $this->fail('Suspended managers must not create demo tickets.');
    } catch (AuthorizationException|HttpException $exception) {
        expect(coreDemoSnapshot())->toBe($before);
    }
});

test('catalog factories support project ownership without changing legacy project defaults', function () {
    expect(Project::factory()->create()->isCore())->toBeFalse();
    $core = Project::factory()->core()->create();
    expect($core->isCore())->toBeTrue()->and($core->timezone)->toBe('UTC');
    foreach ([WorkType::class, Priority::class, Resolution::class] as $model) {
        $catalogs = $model::factory()->count(2)->for($core)->create();
        expect($catalogs->pluck('project_id')->unique()->all())->toBe([$core->id])
            ->and($catalogs->every(fn ($catalog) => $catalog->is_active))->toBeTrue();
        expect($model::factory()->create()->project->isCore())->toBeTrue();
    }
});

test('core demo refuses production without writing any records', function () {
    $before = coreDemoSnapshot();
    $this->app->detectEnvironment(fn () => 'production');

    expect(fn () => $this->seed(CoreDemoSeeder::class))->toThrow(LogicException::class);
    expect(coreDemoSnapshot())->toBe($before);
});

test('database seeder includes core demo after the unchanged legacy demo', function () {
    $this->seed(DatabaseSeeder::class);
    expect(Project::where('process_model', 'legacy')->pluck('code')->all())->toBe(['AST', 'OPS'])
        ->and(Project::where('process_model', 'core')->count())->toBe(2);
    $before = coreDemoSnapshot();
    $this->seed(DatabaseSeeder::class);
    expect(coreDemoSnapshot())->toBe($before);
});

/** @return array<string, array<int, array<string, mixed>>> */
function coreDemoSnapshot(): array
{
    $snapshot = [];
    foreach (['users', 'projects', 'permissions', 'roles', 'role_permissions', 'project_members', 'project_member_roles', 'modules', 'work_types', 'priorities', 'resolutions', 'project_workflows', 'workflow_stages', 'workflow_transitions', 'tickets', 'ticket_activities', 'audit_logs', 'project_ticket_counters'] as $table) {
        $snapshot[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
    }

    return $snapshot;
}
