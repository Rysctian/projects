<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountService
{
    /** @param array{is_active: bool|0|1|'0'|'1', is_system_admin: bool|0|1|'0'|'1'} $data */
    public function updateFlags(User $actor, User $user, array $data): void
    {
        DB::transaction(function () use ($actor, $user, $data) {
            $admins = User::where('is_active', true)->where('is_system_admin', true)->orderBy('id')->lockForUpdate()->get();
            $user = User::lockForUpdate()->findOrFail($user->id);
            if ($user->is_active && $user->is_system_admin && (! $data['is_active'] || ! $data['is_system_admin']) && $admins->count() <= 1) {
                throw ValidationException::withMessages(['account' => 'Keep at least one active System Admin.']);
            }
            $projects = Project::whereIn('id', $user->projectMemberships()->where('is_active', true)->pluck('project_id'))->orderBy('id')->lockForUpdate()->get();
            $before = $user->only(['is_active', 'is_system_admin']);
            $user->forceFill($data)->save();
            if (! $actor->is_system_admin && ! $data['is_active']) {
                foreach ($projects as $project) {
                    ProjectAccess::ensureAccessManager($project);
                }
            }
            if (! $user->is_active) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->forceFill(['remember_token' => null])->save();
            }
            AuditLog::record($actor, $user, 'user.updated', $before, $data);
        });
    }
}
