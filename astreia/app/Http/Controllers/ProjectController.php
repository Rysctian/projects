<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Priority;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Services\ProjectAccess;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', Rule::in(['active', 'archived', 'all'])]]);
        $filters['status'] ??= 'active';
        $projects = Project::visibleTo($request->user())->when($filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))->withCount(['tickets as open_tickets_count' => fn ($q) => $q->whereNull('closed_at')->where('is_cancelled', false)])->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();
        $projects->through(function (Project $project) use ($request): Project {
            if (! $request->user()->hasProjectPermission($project, 'tickets.view')) {
                $project->setAttribute('open_tickets_count', 0);
            }

            return $project;
        });

        return Inertia::render('projects/Index', [
            'projects' => $projects,
            'canCreate' => $request->user()->hasGlobalPermission('projects.create'),
            'filters' => $filters,
            'recoverableProjects' => $request->user()->hasGlobalPermission('projects.recover') ? Project::where('process_model', 'core')->where('status', 'active')->whereNotIn('id', Project::visibleTo($request->user())->select('id'))->orderBy('name')->limit(100)->get(['id', 'code', 'name']) : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasGlobalPermission('projects.create'), 403);
        $data = $request->validate(['code' => ['required', 'regex:/^[A-Z][A-Z0-9]{1,19}$/', 'unique:projects,code'], 'name' => 'required|string|max:255', 'description' => 'nullable|string|max:10000', 'timezone' => 'sometimes|required|timezone:all']);
        $project = DB::transaction(function () use ($request, $data) {
            $project = new Project;
            $project->forceFill([...$data, 'process_model' => 'core', 'created_by' => $request->user()->id])->save();
            ProjectAccess::templates($project);
            $member = $project->members()->create(['user_id' => $request->user()->id]);
            $member->roles()->attach($project->roles()->where('name', 'Project Admin')->firstOrFail()->id, ['project_id' => $project->id]);
            app(WorkflowService::class)->initialize($project, $request->user());
            AuditLog::record($request->user(), $project, 'project.created', after: $data, projectId: $project->id);

            return $project;
        });

        return redirect('/projects/'.$project->id);
    }

    public function recoverAccess(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:10000']);
        ProjectAccess::recover($request->user(), $project, $request->user(), $data['reason']);

        return redirect('/projects/'.$project->id.'/settings');
    }

    public function show(Request $request, Project $project): Response
    {
        ProjectAccess::authorize($request->user(), $project, 'project.view', false);
        $filters = $request->validate([
            'q' => 'nullable|string|max:255', 'category' => ['nullable', Rule::in(Ticket::CATEGORIES)],
            'priority' => ['nullable', Rule::in(Ticket::PRIORITIES)], 'stage_id' => 'nullable|integer',
            'assigned_to' => 'nullable|integer', 'module_id' => 'nullable|integer', 'due' => ['nullable', Rule::in(['overdue', 'today'])],
            'view' => ['nullable', Rule::in(['list', 'board'])],
            'work_type_id' => 'nullable|integer', 'priority_id' => 'nullable|integer',
            'state' => ['nullable', Rule::in(['open', 'resolved'])],
            'sort' => ['nullable', Rule::in(['number', 'title', 'priority', 'due_date', 'updated_at'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        if ($project->isCore()) {
            $filters['view'] = 'list';
        }
        $today = now($project->timezone)->toDateString();
        $canViewTickets = $request->user()->hasProjectPermission($project, 'tickets.view');
        $base = $project->tickets()->when(! $canViewTickets, fn ($q) => $q->whereRaw('1 = 0'));
        $query = (clone $base)->with(['stage', 'assignee:id,name,is_active', 'project']);
        if (filled($filters['q'] ?? null)) {
            $term = $filters['q'];
            if ($project->isCore()) {
                if (preg_match('/^'.preg_quote($project->code, '/').'-0*(\d+)$/i', $term, $matches)) {
                    $query->where('number', (int) $matches[1]);
                } else {
                    $query->whereRaw("title LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%']);
                }
            } else {
                $query->where(function ($q) use ($term, $project) {
                    $q->where('title', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%')->orWhere('description', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%');
                    if (preg_match('/^(?:'.preg_quote($project->code, '/').'-)?0*(\d+)$/i', $term, $matches)) {
                        $q->orWhere('number', (int) $matches[1]);
                    }
                });
            }
        }
        foreach (['category', 'priority', 'stage_id', 'assigned_to', 'module_id', 'work_type_id', 'priority_id'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }
        if (($filters['due'] ?? null) === 'overdue') {
            $query->whereDate('due_date', '<', $today)->whereNull('closed_at')->where('is_cancelled', false);
        } elseif (($filters['due'] ?? null) === 'today') {
            $query->whereDate('due_date', $today);
        }
        if (($filters['state'] ?? null) === 'open') {
            $query->whereNull('closed_at')->where('is_cancelled', false);
        } elseif (($filters['state'] ?? null) === 'resolved') {
            $query->whereNotNull('closed_at');
        }
        if ($project->isCore()) {
            $base = clone $query;
        }
        $sort = $filters['sort'] ?? 'number';
        $direction = $filters['direction'] ?? 'desc';
        if ($sort === 'priority' && $project->isCore()) {
            $query->orderBy(Priority::select('rank')->whereColumn('priorities.id', 'tickets.priority_id'), $direction);
        } else {
            $query->orderBy($sort, $direction);
        }
        $query->orderBy('tickets.id', $direction);

        $stages = WorkflowStage::whereIn('workflow_id', $project->workflows()->where('state', 'published')->pluck('id'))->where('is_active', true)->orderBy('workflow_id')->orderBy('sort_order')->get();
        $byStage = (clone $base)->toBase()->select('stage_id')->selectRaw('COUNT(*) AS total')->groupBy('stage_id')->pluck('total', 'stage_id');
        $byCategory = (clone $base)->toBase()->select('category')->selectRaw('COUNT(*) AS total')->groupBy('category')->pluck('total', 'category');
        $workload = (clone $base)->whereNull('closed_at')->where('is_cancelled', false)->toBase()->select('assigned_to')->selectRaw('COUNT(*) AS total')->groupBy('assigned_to')->get();
        $names = User::whereIn('id', $workload->pluck('assigned_to')->filter())->pluck('name', 'id');
        $boardColumns = [];
        if (($filters['view'] ?? null) === 'board' && $canViewTickets) {
            $matchingStageIds = (clone $query)->toBase()->distinct()->pluck('stage_id')->all();
            $workflowVersions = $project->workflows()->pluck('version_number', 'id');
            foreach ($stages as $stage) {
                if ($stage->workflow_id !== $project->current_workflow_id && ! in_array($stage->id, $matchingStageIds, true)) {
                    continue;
                }
                $boardColumns[] = [
                    'stage' => $stage->only(['id', 'name', 'type']), 'workflow_version' => $workflowVersions[$stage->workflow_id],
                    'tickets' => (clone $query)->where('stage_id', $stage->id)->latest('id')->paginate(20, ['*'], 'board_page_'.$stage->id)->withQueryString(),
                ];
            }
        }

        return Inertia::render('projects/Show', [
            'project' => $project, 'capabilities' => ProjectAccess::capabilities($request->user(), $project),
            'tickets' => (clone $query)->paginate(25)->withQueryString(), 'filters' => $filters,
            'stages' => $stages, 'boardColumns' => $boardColumns,
            'reports' => [
                'by_stage' => $stages->map(fn ($stage) => ['id' => $stage->id, 'name' => $stage->name, 'total' => (int) ($byStage[$stage->id] ?? 0)])->values(),
                'by_category' => array_map(fn ($category) => ['name' => $category, 'total' => (int) ($byCategory[$category] ?? 0)], Ticket::CATEGORIES),
                'workload' => $workload->map(fn ($row) => ['assigned_to' => $row->assigned_to, 'name' => $row->assigned_to ? $names[$row->assigned_to] : 'Unassigned', 'total' => (int) $row->total])->values(),
            ],
            'eligibleAssignees' => TicketController::assignees($project), 'modules' => $project->modules()->where('status', 'active')->get(),
            'categories' => Ticket::CATEGORIES, 'priorities' => Ticket::PRIORITIES, 'labels' => $project->labels()->orderBy('name')->get(),
            'workTypes' => $project->workTypes()->where('is_active', true)->orderBy('id')->get(),
            'priorityOptions' => $project->priorities()->where('is_active', true)->orderBy('rank')->get(),
            'resolutions' => $project->resolutions()->where('is_active', true)->orderBy('id')->get(),
            'counts' => [
                'open' => (clone $base)->whereNull('closed_at')->where('is_cancelled', false)->count(),
                'blocked' => (clone $base)->where('is_blocked', true)->count(),
                'overdue' => (clone $base)->whereNull('closed_at')->where('is_cancelled', false)->whereDate('due_date', '<', $today)->count(),
                'closed' => (clone $base)->whereNotNull('closed_at')->count(),
            ],
        ]);
    }

    public function settings(Request $request, Project $project): Response
    {
        ProjectAccess::authorize($request->user(), $project, 'project.view', false);
        abort_unless(collect(['access.manage', 'members.manage', 'project.manage', 'modules.manage', 'labels.manage'])->contains(fn ($key) => $request->user()->hasProjectPermission($project, $key)), 403);

        return Inertia::render('projects/Settings', [
            'project' => $project,
            'members' => $project->members()->with(['user:id,name,email,is_active', 'roles', 'overrides.permission'])->get(),
            'roles' => $project->roles()->with('permissions')->get(),
            'permissionKeys' => ProjectAccess::KEYS,
            'grantablePermissions' => array_values(array_filter(ProjectAccess::KEYS, fn ($key) => $request->user()->hasProjectPermission($project, $key))),
            'users' => $request->user()->hasProjectPermission($project, 'members.manage') ? User::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']) : [],
            'modules' => $project->modules()->get(),
            'labels' => $project->labels()->orderBy('name')->get(),
            'capabilities' => ProjectAccess::capabilities($request->user(), $project),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        if ($project->isCore()) {
            $data = $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string|max:10000', 'timezone' => 'required|timezone:all', 'status' => ['required', Rule::in(['active', 'archived'])], 'lock_version' => 'required|integer|min:1', 'reason' => 'nullable|string|max:10000']);
            DB::transaction(function () use ($request, $project, $data) {
                $locked = Project::lockForUpdate()->findOrFail($project->id);
                ProjectAccess::authorize($request->user(), $locked, 'project.manage', false);
                if ($locked->lock_version !== (int) $data['lock_version']) {
                    throw ValidationException::withMessages(['lock_version' => 'Project changed. Reload and try again.']);
                }
                $changedStatus = $locked->status !== $data['status'];
                abort_if($locked->status === 'archived' && ! $changedStatus, 403, 'Restore the project before editing it.');
                if ($changedStatus && ! filled($data['reason'] ?? null)) {
                    throw ValidationException::withMessages(['reason' => 'Explain the archive or restoration.']);
                }
                $before = $locked->only(['name', 'description', 'timezone', 'status']);
                // Restoration is a separate lifecycle operation; ignore bundled domain edits.
                $fields = $locked->status === 'archived' ? ['status' => 'active'] : Arr::only($data, ['name', 'description', 'timezone', 'status']);
                $locked->update([...$fields, 'archived_at' => $data['status'] === 'archived' ? now() : null, 'lock_version' => $locked->lock_version + 1]);
                AuditLog::record($request->user(), $locked, $changedStatus ? 'project.'.$data['status'] : 'project.updated', $before, [...$fields, 'reason' => $data['reason'] ?? null], $locked->id);
            });

            return back();
        }
        $data = $request->validate(['name' => 'required|string|max:255', 'description' => 'nullable|string|max:10000', 'status' => ['required', Rule::in(['active', 'archived'])]]);
        ProjectAccess::change($request->user(), $project, 'project.manage', function () use ($request, $project, $data) {
            $before = $project->only(['name', 'description', 'status']);
            $project->update([...$data, 'archived_at' => $data['status'] === 'archived' ? ($project->archived_at ?? now()) : null]);
            AuditLog::record($request->user(), $project, 'project.updated', $before, $data, $project->id);
        });

        return back();
    }

    public function storeModule(Request $request, Project $project): RedirectResponse
    {
        ProjectAccess::authorize($request->user(), $project, 'modules.manage');
        $data = $request->validate(['code' => ['required', 'string', 'max:30', Rule::unique('modules')->where('project_id', $project->id)], 'name' => 'required|string|max:255']);
        DB::transaction(function () use ($request, $project, $data) {
            $project = Project::lockForUpdate()->findOrFail($project->id);
            ProjectAccess::authorize($request->user(), $project, 'modules.manage');
            $module = $project->modules()->create($data);
            AuditLog::record($request->user(), $module, 'module.created', after: $data, projectId: $project->id);
        });

        return back();
    }

    public function updateModule(Request $request, Project $project, int $module): RedirectResponse
    {
        ProjectAccess::authorize($request->user(), $project, 'modules.manage');
        $module = $project->modules()->findOrFail($module);
        $data = $request->validate(['name' => 'required|string|max:255', 'status' => ['required', Rule::in(['active', 'archived'])]]);
        DB::transaction(function () use ($request, $project, $module, $data) {
            $project = Project::lockForUpdate()->findOrFail($project->id);
            ProjectAccess::authorize($request->user(), $project, 'modules.manage');
            $before = $module->only(['name', 'status']);
            $module->update($data);
            AuditLog::record($request->user(), $module, 'module.updated', $before, $data, $project->id);
        });

        return back();
    }
}
