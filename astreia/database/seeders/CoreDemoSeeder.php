<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ProjectAccess;
use App\Services\TicketService;
use App\Services\WorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

class CoreDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('Core demo data may only be seeded outside production.');
        }

        DB::transaction(function (): void {
            foreach ([
                ['COREAPP', 'Core Product Workspace', 'Asia/Singapore', 'core-manager', 'Morgan Product Manager'],
                ['COREOPS', 'Core Operations Workspace', 'UTC', 'core-ops-manager', 'Olivia Operations Manager'],
            ] as [$code, $name, $timezone, $managerEmail, $managerName]) {
                // A project code is the insert-only marker; never repair or reset an existing workspace.
                if (Project::where('code', $code)->exists()) {
                    continue;
                }

                ProjectAccess::seedCatalog();
                $this->user('admin', 'Astreia Admin', admin: true);
                $manager = $this->user($managerEmail, $managerName);
                $worker = $this->user('core-worker', 'Alex Core Worker');
                $project = Project::factory()->core()->create([
                    'code' => $code, 'name' => $name, 'timezone' => $timezone,
                    'description' => 'Scoped core ticketing with intake, active work, outcomes and reopen history.',
                    'created_by' => $manager->id,
                ]);
                $this->access($project, $manager, $worker);
                app(WorkflowService::class)->initialize($project, $manager);
                $this->tickets($project->refresh(), $manager, $worker);
            }
        });
    }

    private function user(string $email, string $name, bool $active = true, bool $admin = false): User
    {
        return User::firstOrCreate(['email' => $email.'@astreia.test'], [
            'name' => $name, 'password' => Hash::make('password'), 'email_verified_at' => now(),
            'is_active' => $active, 'is_system_admin' => $admin,
        ]);
    }

    private function access(Project $project, User $manager, User $worker): void
    {
        $workKeys = ['project.view', 'tickets.view', 'tickets.create', 'tickets.edit', 'tickets.assign', 'tickets.work', 'tickets.transition', 'tickets.close'];
        $this->role($project, 'Core Access Manager', ['project.manage', 'modules.manage', 'members.manage', 'access.manage', 'workflows.manage', ...$workKeys], [$manager]);
        $this->role($project, 'Core Worker', $workKeys, [$worker]);

        if ($project->code === 'COREAPP') {
            $this->role($project, 'Core Viewer', ['project.view', 'tickets.view'], [$this->user('core-viewer', 'Val Core Viewer')]);
            $this->role($project, 'Suspended Core Worker', $workKeys, [$this->user('core-suspended', 'Sam Suspended', active: false)]);
        }
    }

    /**
     * @param  list<string>  $keys
     * @param  list<User>  $users
     */
    private function role(Project $project, string $name, array $keys, array $users): void
    {
        $role = $project->roles()->create(['name' => $name, 'is_active' => true]);
        $role->permissions()->attach(Permission::where('scope', 'project')->whereIn('key', $keys)->pluck('id'));
        foreach ($users as $user) {
            $member = $project->members()->create(['user_id' => $user->id, 'is_active' => true]);
            $member->roles()->attach($role->id, ['project_id' => $project->id]);
        }
    }

    private function tickets(Project $project, User $manager, User $worker): void
    {
        $service = app(TicketService::class);
        $module = $project->modules()->create(['code' => 'SERVICE', 'name' => 'Staff Service', 'status' => 'active']);
        $today = now($project->timezone)->startOfDay();
        $samples = [
            ['Clarify staff onboarding needs', 'Collect the team requirements before estimating the onboarding work.', 'story', 'normal', null, null, 'open', null],
            ['Confirm the published help link', 'Check the existing help link; no implementation effort is expected.', 'task', 'low', 0, null, 'open', null],
            ['Fix missing staff search results', 'Restore lookup by staff reference and verify existing results remain available.', 'bug', 'urgent', 90, $today->copy()->subDays(2)->toDateString(), 'in_progress', null],
            ['Publish the onboarding checklist', 'Give new staff a concise checklist with the approved onboarding steps.', 'task', 'normal', 60, $today->copy()->subDay()->toDateString(), 'resolved', 'done'],
            ['Consolidate duplicate access requests', 'Confirm this request is already tracked by the staff onboarding work.', 'bug', 'high', 15, null, 'resolved', 'duplicate'],
            ['Recheck keyboard access to staff search', 'Verify keyboard focus remains visible throughout the staff search flow.', 'story', 'high', 120, $today->copy()->addDays(3)->toDateString(), 'reopened', 'done'],
        ];

        foreach ($samples as [$title, $description, $type, $priority, $estimate, $due, $state, $outcome]) {
            $ticket = $service->create($project, $manager, [
                'title' => $title, 'description' => $description,
                'work_type_id' => $project->workTypes()->where('code', $type)->value('id'),
                'priority_id' => $project->priorities()->where('code', $priority)->value('id'),
                'assigned_to' => $state === 'open' ? null : $worker->id,
                'module_id' => $module->id, 'estimate_minutes' => $estimate, 'due_date' => $due,
                'acceptance_criteria' => $type === 'story' ? ['Staff can complete the described task using a keyboard.'] : [],
            ]);
            if ($ticket->assigned_to === null) {
                continue;
            }
            $ticket = $this->advance($project, $worker, $ticket, 'in_progress');
            if ($state === 'in_progress') {
                continue;
            }
            $ticket = $this->advance($project, $worker, $ticket, 'resolved', [
                'resolution_id' => $project->resolutions()->where('code', $outcome)->value('id'),
                'completion_notes' => $outcome === 'duplicate'
                    ? 'The original onboarding request already covers this work; keep its history together.'
                    : 'Completed the work and verified the described staff journey.',
            ]);
            if ($state === 'reopened') {
                $service->action($project, $worker, $ticket, [
                    'action' => 'reopen', 'lock_version' => $ticket->lock_version,
                    'reason' => 'Follow-up keyboard testing found a missing focus indicator.',
                ]);
            }
        }
    }

    /** @param array<string, mixed> $extra */
    private function advance(Project $project, User $actor, Ticket $ticket, string $stage, array $extra = []): Ticket
    {
        $transition = $ticket->workflow->transitions()->where('from_stage_id', $ticket->stage_id)
            ->whereHas('toStage', fn ($query) => $query->where('key', $stage))->firstOrFail();

        return app(TicketService::class)->transition($project, $actor, $ticket, [
            'transition_id' => $transition->id, 'lock_version' => $ticket->lock_version, ...$extra,
        ]);
    }
}
