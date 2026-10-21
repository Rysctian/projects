<?php

namespace Database\Factories;

use App\Models\AccountInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AccountInvitation> */
class AccountInvitationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['password_setup_required' => true, 'email_verified_at' => null]),
            'email' => fn (array $attributes) => User::findOrFail($attributes['user_id'])->email,
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addHour(),
        ];
    }
}
