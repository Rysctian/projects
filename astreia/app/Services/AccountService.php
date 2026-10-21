<?php

namespace App\Services;

use App\Models\AccountInvitation;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AccountService
{
    /** @param array{is_active: bool|0|1|'0'|'1', is_system_admin: bool|0|1|'0'|'1'} $data */
    public function updateFlags(User $actor, User $user, array $data): void
    {
        DB::transaction(function () use ($actor, $user, $data) {
            $admins = User::where('is_active', true)->where(fn ($query) => $query->where('is_system_admin', true)
                ->orWhereHas('globalPermissions', fn ($query) => $query->where('scope', 'global')->where('key', 'users.manage')))->orderBy('id')->lockForUpdate()->get();
            $user = User::lockForUpdate()->findOrFail($user->id);
            $actor = $actor->fresh();
            $selfDeactivation = $actor->id === $user->id && ! $data['is_active'] && (bool) $data['is_system_admin'] === $user->is_system_admin;
            abort_unless($actor->is_active && ($actor->hasGlobalPermission('users.manage') || $selfDeactivation), 403);
            if ($data['is_system_admin'] && ! $user->is_system_admin) {
                foreach (User::GLOBAL_PERMISSIONS as $permission) {
                    abort_unless($actor->hasGlobalPermission($permission), 403, 'You may not grant global permissions you do not possess.');
                }
            }
            if ($user->is_active && $user->is_system_admin && (! $data['is_active'] || ! $data['is_system_admin']) && $admins->where('is_system_admin', true)->count() <= 1) {
                throw ValidationException::withMessages(['account' => 'Keep at least one active System Admin.']);
            }
            $projects = Project::whereIn('id', $user->projectMemberships()->where('is_active', true)->pluck('project_id'))->orderBy('id')->lockForUpdate()->get();
            $before = $user->only(['is_active', 'is_system_admin']);
            $managedAccounts = $user->hasGlobalPermission('users.manage');
            $user->forceFill($data)->save();
            if ($managedAccounts && ! User::where('is_active', true)->whereHas('globalPermissions', fn ($query) => $query->where('scope', 'global')->where('key', 'users.manage'))->exists()) {
                throw ValidationException::withMessages(['account' => 'Keep at least one active account administrator.']);
            }
            if (! $data['is_active']) {
                foreach ($projects as $project) {
                    if ($project->isCore() || ! $actor->is_system_admin) {
                        ProjectAccess::ensureAccessManager($project);
                    }
                }
            }
            if (! $user->is_active) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->forceFill(['remember_token' => null])->save();
            }
            AuditLog::record($actor, $user, 'user.updated', $before, $data);
        });
    }

    public function sendInvitation(User $actor, User $user): string
    {
        $token = null;
        $status = DB::transaction(function () use ($actor, $user, &$token) {
            abort_unless($actor->fresh()->hasGlobalPermission('users.manage'), 403);
            $user = User::lockForUpdate()->findOrFail($user->id);
            abort_unless($user->is_active && $user->password_setup_required, 422, 'Only active accounts awaiting initial setup can be invited.');

            return Password::broker(config('fortify.passwords'))->sendResetLink(['email' => $user->email], function (User $recipient, string $setupToken) use ($actor, &$token) {
                $recipient->invitations()->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
                $broker = config('fortify.passwords') ?: config('auth.defaults.passwords');
                AccountInvitation::create([
                    'user_id' => $recipient->id, 'invited_by' => $actor->id, 'email' => $recipient->email,
                    'token_hash' => hash('sha256', $setupToken),
                    'expires_at' => now()->addMinutes((int) config('auth.passwords.'.$broker.'.expire', 60)),
                ]);
                $token = $setupToken;
            });
        });
        if ($status === Password::RESET_LINK_SENT && $token !== null) {
            $user->notify(new ResetPassword($token));
            AuditLog::record($actor, $user, 'user.invitation_sent');
        }

        return $status;
    }
}
