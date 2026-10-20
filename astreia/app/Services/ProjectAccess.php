<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProjectAccess
{
    public const KEYS = [
        'project.view', 'project.manage', 'modules.manage', 'members.manage', 'access.manage',
        'workflows.manage', 'tickets.view', 'tickets.create', 'tickets.edit', 'tickets.assign',
        'tickets.work', 'tickets.transition', 'tickets.verify', 'tickets.close', 'tickets.comment',
        'tickets.attach', 'labels.manage',
    ];

    public static function seedCatalog(): void
    {
        foreach (self::KEYS as $key) {
            Permission::firstOrCreate(['key' => $key], ['description' => ucfirst(str_replace('.', ' ', $key)), 'scope' => 'project']);
        }
    }

    public static function authorize(User $actor, Project $project, string $key, bool $write = true): void
    {
        Gate::forUser($actor)->authorize('project-permission', [$project, $key]);
        abort_unless($actor->hasProjectPermission($project, 'project.view'), 403);
        abort_if($write && $project->status === 'archived' && ! $actor->is_system_admin, 403, 'Archived projects are read-only.');
    }

    /** @return array<string, bool> */
    public static function capabilities(User $actor, Project $project): array
    {
        return collect(self::KEYS)->mapWithKeys(fn ($key) => [$key => $actor->hasProjectPermission($project, $key)
            && ($key === 'project.view' || $key === 'tickets.view' || $project->status !== 'archived' || $actor->is_system_admin)])->all();
    }

    /** @param array<array-key, string> $keys */
    public static function requireGrantable(User $actor, Project $project, array $keys): void
    {
        foreach ($keys as $key) {
            abort_unless(in_array($key, self::KEYS, true) && $actor->hasProjectPermission($project, $key), 403, 'You may not grant permissions you do not possess.');
        }
    }

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $operation
     * @return TResult
     */
    public static function change(User $actor, Project $project, string $permission, callable $operation): mixed
    {
        return DB::transaction(function () use ($actor, $project, $permission, $operation) {
            $project = Project::lockForUpdate()->findOrFail($project->id);
            self::authorize($actor, $project, $permission);
            $result = $operation();
            if (! $actor->is_system_admin) {
                self::ensureAccessManager($project);
            }

            return $result;
        });
    }

    public static function ensureAccessManager(Project $project): void
    {
        $hasManager = $project->members()->where('is_active', true)->with('user')->get()
            ->contains(fn ($member) => $member->user->is_active && $member->user->hasProjectPermission($project, 'project.view') && $member->user->hasProjectPermission($project, 'access.manage'));
        if (! $hasManager) {
            throw ValidationException::withMessages(['access' => 'Keep an active project member able to manage access, or ask a System Admin.']);
        }
    }

    public static function templates(Project $project): void
    {
        self::seedCatalog();
        $templates = [
            'Project Admin' => self::KEYS,
            'Coordinator' => ['project.view', 'tickets.view', 'tickets.create', 'tickets.edit', 'tickets.assign', 'tickets.transition', 'tickets.verify', 'tickets.close', 'tickets.comment', 'tickets.attach'],
            'Developer' => ['project.view', 'tickets.view', 'tickets.work', 'tickets.transition', 'tickets.comment', 'tickets.attach'],
            'QA' => ['project.view', 'tickets.view', 'tickets.verify', 'tickets.transition', 'tickets.comment', 'tickets.attach'],
            'Viewer' => ['project.view', 'tickets.view'],
        ];
        foreach ($templates as $name => $keys) {
            $role = $project->roles()->create(['name' => $name]);
            $role->permissions()->sync(Permission::whereIn('key', $keys)->where('scope', 'project')->pluck('id'));
        }
    }
}
