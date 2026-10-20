<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Module;
use App\Models\Project;
use App\Models\ProjectLabel;
use App\Models\ProjectMember;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\TicketStageSignoff;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Services\ProjectAccess;
use App\Services\WorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

class AstreiaDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new LogicException('Astreia demo data may only be seeded outside production.');
        }

        DB::transaction(function (): void {
            ProjectAccess::seedCatalog();
            $users = $this->users();
            $astreia = $this->project('AST', 'Astreia Launch', 'A practical launch workspace with work at every core workflow stage.', $users['admin']);
            $operations = $this->project('OPS', 'Operations Portal', 'A second project that makes project-scoped access and reporting visible.', $users['admin']);

            $this->access($astreia, $users, ['admin' => 'Project Admin', 'coordinator' => 'Coordinator', 'developer' => 'Developer', 'qa' => 'QA', 'reporter' => 'Coordinator', 'viewer' => 'Viewer']);
            $this->access($operations, $users, ['admin' => 'Project Admin', 'coordinator' => 'Coordinator', 'developer' => 'Developer', 'qa' => 'QA']);
            $this->member($astreia, $users['viewer'])->overrides()->firstOrCreate(
                ['permission_id' => DB::table('permissions')->where('key', 'tickets.comment')->value('id')],
                ['effect' => 'allow'],
            );

            $modules = [
                'identity' => $astreia->modules()->firstOrCreate(['code' => 'IDENTITY'], ['name' => 'Identity and Access', 'status' => 'active']),
                'workflow' => $astreia->modules()->firstOrCreate(['code' => 'WORKFLOW'], ['name' => 'Workflow', 'status' => 'active']),
                'reporting' => $operations->modules()->firstOrCreate(['code' => 'REPORTS'], ['name' => 'Reporting', 'status' => 'active']),
            ];
            $labels = [
                'urgent' => $this->label($astreia, 'urgent'), 'accessibility' => $this->label($astreia, 'accessibility'),
                'customer' => $this->label($astreia, 'customer-request'), 'operations' => $this->label($operations, 'operations'),
            ];

            $this->astreiaTickets($astreia, $users, $modules, $labels);
            $this->operationsTickets($operations, $users, $modules, $labels);
            $this->counter($astreia);
            $this->counter($operations);
            $this->audit($astreia, $operations, $users['admin']);
        });
    }

    /** @return array<string, User> */
    private function users(): array
    {
        $data = [
            'admin' => ['Astreia Admin', 'admin@astreia.test', true, true],
            'coordinator' => ['Casey Coordinator', 'coordinator@astreia.test', true, false],
            'developer' => ['Devon Developer', 'developer@astreia.test', true, false],
            'qa' => ['Quinn QA', 'qa@astreia.test', true, false],
            'reporter' => ['Riley Reporter', 'reporter@astreia.test', true, false],
            'viewer' => ['Val Viewer', 'viewer@astreia.test', true, false],
            'inactive' => ['Indy Inactive', 'inactive@astreia.test', false, false],
        ];

        return collect($data)->mapWithKeys(function (array $user, string $key): array {
            return [$key => User::firstOrCreate(['email' => $user[1]], [
                'name' => $user[0], 'password' => Hash::make('password'), 'email_verified_at' => now(),
                'is_active' => $user[2], 'is_system_admin' => $user[3],
            ])];
        })->all();
    }

    private function project(string $code, string $name, string $description, User $owner): Project
    {
        $project = Project::firstOrCreate(['code' => $code], ['name' => $name, 'description' => $description, 'created_by' => $owner->id, 'status' => 'active']);
        if ($project->roles()->doesntExist()) {
            ProjectAccess::templates($project);
        }
        if (! $project->current_workflow_id) {
            app(WorkflowService::class)->initialize($project, $owner);
            $project->refresh();
        }

        return $project;
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, string>  $roles
     */
    private function access(Project $project, array $users, array $roles): void
    {
        foreach ($roles as $userKey => $roleName) {
            $member = $this->member($project, $users[$userKey]);
            $role = $project->roles()->where('name', $roleName)->firstOrFail();
            $member->roles()->syncWithoutDetaching([$role->id => ['project_id' => $project->id]]);
        }
    }

    private function member(Project $project, User $user): ProjectMember
    {
        return $project->members()->firstOrCreate(['user_id' => $user->id], ['is_active' => true]);
    }

    private function label(Project $project, string $name): ProjectLabel
    {
        return $project->labels()->firstOrCreate(['name' => $name]);
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Module>  $modules
     * @param  array<string, ProjectLabel>  $labels
     */
    private function astreiaTickets(Project $project, array $users, array $modules, array $labels): void
    {
        $backlog = $this->ticket($project, 1, 'backlog', $users['reporter'], ['title' => 'Review invited-user language', 'description' => 'Confirm the invitation copy is clear before the customer rollout.', 'category' => 'documentation', 'priority' => 'normal', 'module_id' => $modules['identity']->id, 'acceptance_criteria' => ['Invitation copy names the next step clearly.'], 'due_date' => now()->addDays(4)->toDateString()]);
        $development = $this->ticket($project, 2, 'development', $users['coordinator'], ['title' => 'Show a member’s inherited project capabilities', 'description' => 'Project managers need to understand how a member’s effective permissions are assembled.', 'category' => 'new_feature', 'priority' => 'high', 'module_id' => $modules['identity']->id, 'assigned_to' => $users['developer']->id, 'estimate_minutes' => 360, 'acceptance_criteria' => ['Show role grants and explicit overrides.', 'Keep project data scoped to the current project.'], 'due_date' => now()->addDays(8)->toDateString()]);
        $qa = $this->ticket($project, 3, 'qa', $users['coordinator'], ['title' => 'Keep reopened tickets visible in the project board', 'description' => 'A reopened ticket must retain its permanent key and return to intake.', 'category' => 'bug_fix', 'priority' => 'high', 'module_id' => $modules['workflow']->id, 'assigned_to' => $users['developer']->id, 'estimate_minutes' => 180, 'acceptance_criteria' => ['Reopen retains the ticket key.', 'Prior QA approval no longer permits closure.'], 'due_date' => now()->addDays(2)->toDateString()]);
        $closed = $this->ticket($project, 4, 'closed', $users['coordinator'], ['title' => 'Add meaningful project board totals', 'description' => 'The project overview needs a concise report by stage and workload.', 'category' => 'enhancement', 'priority' => 'normal', 'module_id' => $modules['workflow']->id, 'assigned_to' => $users['developer']->id, 'estimate_minutes' => 240, 'acceptance_criteria' => ['Board shows current ticket counts.', 'Workload includes unassigned work.'], 'completion_notes' => 'Added project-level board totals and workload reporting.', 'documentation_impact' => 'required', 'documentation_impact_note' => 'Add a short reporting guide for coordinators.', 'closed_by' => $users['coordinator']->id, 'closed_at' => now()->subDay()]);
        $blocked = $this->ticket($project, 5, 'ready', $users['reporter'], ['title' => 'Clarify external support request', 'description' => 'Waiting for the customer to provide the affected ticket key.', 'category' => 'support', 'priority' => 'urgent', 'module_id' => $modules['identity']->id, 'acceptance_criteria' => ['Customer supplies a reproducible example.'], 'is_blocked' => true, 'blocked_from_stage_id' => $this->stage($project, 'ready')->id]);

        $this->labels($development, [$labels['accessibility'], $labels['customer']]);
        $this->labels($qa, [$labels['urgent']]);
        $this->labels($blocked, [$labels['urgent'], $labels['customer']]);
        $this->comment($backlog, $users['viewer'], 'I can confirm the customer-facing copy needs a plain-language final pass.');
        $this->comment($development, $users['developer'], 'The role and override explanation is ready for coordinator review.');
        $this->comment($qa, $users['qa'], 'Regression coverage is ready; please verify the board behavior.');
        $this->activity($development, $users['developer'], 'transitioned', ['to_stage' => 'development']);
        $this->activity($qa, $users['coordinator'], 'transitioned', ['to_stage' => 'qa']);
        $this->activity($closed, $users['coordinator'], 'closed', ['completion_notes' => $closed->completion_notes]);
        $this->activity($blocked, $users['reporter'], 'blocked', ['reason' => 'Waiting for a reproducible example.']);
        $this->signoff($closed, $users['qa'], 'pass', 'Verified the board totals against the seeded workload.');
        $this->attachment($development, $users['developer']);
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Module>  $modules
     * @param  array<string, ProjectLabel>  $labels
     */
    private function operationsTickets(Project $project, array $users, array $modules, array $labels): void
    {
        $ready = $this->ticket($project, 1, 'ready', $users['coordinator'], ['title' => 'Confirm weekly operations export fields', 'description' => 'Agree the baseline data points for the operations review.', 'category' => 'documentation', 'priority' => 'normal', 'module_id' => $modules['reporting']->id, 'acceptance_criteria' => ['Include open, blocked, and closed ticket counts.']]);
        $closed = $this->ticket($project, 2, 'closed', $users['coordinator'], ['title' => 'Correct overdue ticket calculation', 'description' => 'Closed tickets should not appear in the overdue total.', 'category' => 'bug_fix', 'priority' => 'high', 'module_id' => $modules['reporting']->id, 'assigned_to' => $users['developer']->id, 'acceptance_criteria' => ['Only open non-cancelled tickets can be overdue.'], 'completion_notes' => 'Excluded closed and cancelled tickets from overdue reporting.', 'documentation_impact' => 'not_required', 'closed_by' => $users['coordinator']->id, 'closed_at' => now()->subDays(2)]);
        $this->labels($ready, [$labels['operations']]);
        $this->labels($closed, [$labels['operations']]);
        $this->comment($closed, $users['qa'], 'Verified the corrected count against representative ticket states.');
        $this->activity($closed, $users['coordinator'], 'closed', ['completion_notes' => $closed->completion_notes]);
        $this->signoff($closed, $users['qa'], 'pass', 'QA confirmed the overdue filter.');
    }

    /** @param array<string, mixed> $attributes */
    private function ticket(Project $project, int $number, string $stageKey, User $reporter, array $attributes): Ticket
    {
        $stage = $this->stage($project, $stageKey);

        return Ticket::firstOrCreate(['project_id' => $project->id, 'number' => $number], [...$attributes, 'workflow_id' => $stage->workflow_id, 'stage_id' => $stage->id, 'created_by' => $reporter->id]);
    }

    private function stage(Project $project, string $key): WorkflowStage
    {
        return $project->workflows()->findOrFail($project->current_workflow_id)->stages()->where('key', $key)->firstOrFail();
    }

    /** @param array<int, ProjectLabel> $labels */
    private function labels(Ticket $ticket, array $labels): void
    {
        foreach ($labels as $label) {
            DB::table('ticket_labels')->updateOrInsert(['ticket_id' => $ticket->id, 'project_label_id' => $label->id], ['project_id' => $ticket->project_id]);
        }
    }

    private function comment(Ticket $ticket, User $author, string $body): void
    {
        TicketComment::firstOrCreate(['ticket_id' => $ticket->id, 'author_id' => $author->id, 'body' => $body]);
    }

    /** @param array<string, mixed> $payload */
    private function activity(Ticket $ticket, User $actor, string $type, array $payload): void
    {
        TicketActivity::firstOrCreate(['ticket_id' => $ticket->id, 'activity_type' => $type], ['actor_id' => $actor->id, 'payload' => $payload, 'created_at' => now()]);
    }

    private function signoff(Ticket $ticket, User $actor, string $result, string $note): void
    {
        $verificationStage = $ticket->workflow()->firstOrFail()->stages()->where('type', 'verification')->firstOrFail();
        TicketStageSignoff::firstOrCreate(['ticket_id' => $ticket->id, 'stage_id' => $verificationStage->id, 'actor_id' => $actor->id, 'kind' => 'qa', 'result' => $result], ['note' => $note, 'verified_revision' => $ticket->revision_number, 'stage_visit' => $ticket->stage_visit, 'verification_cycle' => $ticket->verification_cycle, 'created_at' => now()]);
    }

    private function attachment(Ticket $ticket, User $uploader): void
    {
        TicketAttachment::firstOrCreate(['ticket_id' => $ticket->id, 'storage_path' => 'tickets/demo/'.$ticket->id.'/capabilities-note.txt'], ['uploaded_by' => $uploader->id, 'storage_disk' => 'private', 'original_filename' => 'capabilities-note.txt', 'mime_type' => 'text/plain', 'size_bytes' => 326]);
    }

    private function counter(Project $project): void
    {
        DB::table('project_ticket_counters')->updateOrInsert(['project_id' => $project->id], ['next_number' => ((int) $project->tickets()->max('number')) + 1]);
    }

    private function audit(Project $astreia, Project $operations, User $actor): void
    {
        foreach ([$astreia, $operations] as $project) {
            AuditLog::firstOrCreate(['actor_id' => $actor->id, 'project_id' => $project->id, 'entity_type' => Project::class, 'entity_id' => $project->id, 'action' => 'demo.seeded'], ['after_json' => ['source' => 'AstreiaDemoSeeder'], 'created_at' => now()]);
        }
    }
}
