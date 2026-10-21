<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        DB::transaction(function () use ($user, $input) {
            $locked = User::lockForUpdate()->findOrFail($user->id);
            $token = $input['token'] ?? '';
            $invitation = $locked->invitations()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $locked->is_active || ! Password::broker(config('fortify.passwords'))->tokenExists($locked, $token)
                || ($locked->password_setup_required && ! $invitation)
                || ($invitation && (! $locked->password_setup_required || $invitation->accepted_at || $invitation->revoked_at
                    || $invitation->expires_at->isPast() || $invitation->email !== $locked->email))) {
                throw ValidationException::withMessages(['email' => 'This password setup or reset link is invalid or expired.']);
            }
            $locked->forceFill([
                'password' => $input['password'],
                'email_verified_at' => $locked->email_verified_at ?? now(),
                'password_setup_required' => false,
                'remember_token' => Str::random(60),
            ])->save();
            if ($invitation) {
                $invitation->update(['accepted_at' => now()]);
                $locked->invitations()->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }
            Password::broker(config('fortify.passwords'))->deleteToken($locked);
            $user->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
