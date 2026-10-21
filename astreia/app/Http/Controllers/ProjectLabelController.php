<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use App\Services\ProjectAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectLabelController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_if($project->isCore(), 403, 'Labels are not enabled for core projects.');
        DB::transaction(function () use ($request, $project) {
            $project = Project::lockForUpdate()->findOrFail($project->id);
            ProjectAccess::authorize($request->user(), $project, 'labels.manage');
            $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('project_labels')->where('project_id', $project->id)]]);
            $label = $project->labels()->create($data);
            AuditLog::record($request->user(), $label, 'label.created', after: $data, projectId: $project->id);
        });

        return back();
    }
}
