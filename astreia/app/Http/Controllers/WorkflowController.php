<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectAccess;
use App\Services\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        ProjectAccess::authorize($request->user(), $project, 'workflows.manage', false);

        return Inertia::render('projects/Workflow', [
            'project' => $project,
            'workflows' => $project->workflows()->with(['stages', 'transitions.requiredPermission'])->orderByDesc('version_number')->get(),
            'permissionKeys' => ProjectAccess::KEYS,
            'stageTypes' => WorkflowService::TYPES,
            'capabilities' => ProjectAccess::capabilities($request->user(), $project),
        ]);
    }

    public function draft(Request $request, Project $project, WorkflowService $service): RedirectResponse
    {
        $service->saveDraft($project, $request->user(), $request->all());

        return back();
    }

    public function publish(Request $request, Project $project, int $workflow, WorkflowService $service): RedirectResponse
    {
        $service->publish($project, $request->user(), $project->workflows()->findOrFail($workflow));

        return back();
    }
}
