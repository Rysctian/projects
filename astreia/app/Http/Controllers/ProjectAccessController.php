<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Project;
use App\Services\ProjectAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class ProjectAccessController extends Controller
{
    public function role(Request $request, Project $project, ?int $role = null): RedirectResponse
    {
        ProjectAccess::change($request->user(), $project, 'access.manage', function () use ($request, $project, $role) {
            $existing = $role ? $project->roles()->findOrFail($role) : null;
            $data = $request->validate([
                'name' => ['required', 'string', 'max:100', Rule::unique('roles')->where('project_id', $project->id)->ignore($role)],
                'description' => 'nullable|string|max:2000', 'is_active' => 'sometimes|boolean',
                'permissions' => 'present|array', 'permissions.*' => ['required', 'distinct', Rule::in(ProjectAccess::KEYS)],
            ]);
            ProjectAccess::requireGrantable($request->user(), $project, array_unique([...$data['permissions'], ...($existing?->permissions()->pluck('key')->all() ?? [])]));
            $before = $existing?->load('permissions')->toArray();
            $record = $existing ?? $project->roles()->make();
            $record->fill(Arr::except($data, 'permissions'))->save();
            $record->permissions()->sync(Permission::where('scope', 'project')->whereIn('key', $data['permissions'])->pluck('id'));
            AuditLog::record($request->user(), $record, 'role.saved', $before, $record->load('permissions')->toArray(), $project->id);
        });

        return back();
    }

    public function member(Request $request, Project $project, ?int $member = null): RedirectResponse
    {
        ProjectAccess::change($request->user(), $project, 'members.manage', function () use ($request, $project, $member) {
            $record = $member ? $project->members()->findOrFail($member) : null;
            $data = $request->validate([
                'user_id' => [$record ? 'sometimes' : 'required', Rule::exists('users', 'id')->where('is_active', true)],
                'is_active' => 'sometimes|boolean', 'role_ids' => 'present|array',
                'role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->where('project_id', $project->id)->where('is_active', true)],
            ]);
            $record ??= $project->members()->where('user_id', $data['user_id'])->first();
            if ($record) {
                $existingKeys = $record->roles()->with('permissions')->get()->flatMap(fn ($role) => $role->permissions->pluck('key'))->all();
                $overrideKeys = $record->overrides()->where('effect', 'allow')->with('permission')->get()->map(fn ($override) => $override->permission->key)->all();
                ProjectAccess::requireGrantable($request->user(), $project, array_unique([...$existingKeys, ...$overrideKeys]));
            }
            $roles = $project->roles()->whereIn('id', $data['role_ids'])->with('permissions')->get();
            ProjectAccess::requireGrantable($request->user(), $project, $roles->flatMap(fn ($role) => $role->permissions->pluck('key'))->unique()->all());
            abort_unless($request->user()->hasProjectPermission($project, 'access.manage') || ($record && $record->roles()->pluck('roles.id')->sort()->values()->all() === collect(array_values($data['role_ids']))->sort()->values()->all()), 403);
            $before = $record?->load('roles')->toArray();
            $record ??= $project->members()->firstOrNew(['user_id' => $data['user_id']]);
            $record->is_active = $data['is_active'] ?? true;
            $record->save();
            $record->roles()->sync(collect(array_values($data['role_ids']))->mapWithKeys(fn ($id) => [$id => ['project_id' => $project->id]])->all());
            AuditLog::record($request->user(), $record, 'member.saved', $before, $record->load('roles')->toArray(), $project->id);
        });

        return back();
    }

    public function overrides(Request $request, Project $project, int $member): RedirectResponse
    {
        ProjectAccess::change($request->user(), $project, 'access.manage', function () use ($request, $project, $member) {
            $member = $project->members()->findOrFail($member);
            $data = $request->validate(['overrides' => 'present|array', 'overrides.*.key' => ['required', 'distinct', Rule::in(ProjectAccess::KEYS)], 'overrides.*.effect' => ['required', Rule::in(['allow', 'deny'])]]);
            $before = $member->overrides()->with('permission')->get()->toArray();
            ProjectAccess::requireGrantable($request->user(), $project, array_unique([...array_column($data['overrides'], 'key'), ...array_column(array_column($before, 'permission'), 'key')]));
            $member->overrides()->delete();
            foreach ($data['overrides'] as $override) {
                $member->overrides()->create(['permission_id' => Permission::where('key', $override['key'])->where('scope', 'project')->firstOrFail()->id, 'effect' => $override['effect']]);
            }
            AuditLog::record($request->user(), $member, 'member.overrides', $before, $data, $project->id);
        });

        return back();
    }
}
