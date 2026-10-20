<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BootstrapAdmin extends Command
{
    protected $signature = 'astreia:bootstrap-admin {--name=} {--email=}';

    protected $description = 'Securely create the first system administrator (password is never an argument)';

    public function handle(): int
    {
        if (User::where('is_active', true)->where('is_system_admin', true)->exists()) {
            $this->error('An active system administrator already exists.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->option('name') ?: $this->ask('Name'),
            'email' => strtolower($this->option('email') ?: $this->ask('Email')),
            'password' => $this->secret('Password (minimum 12 characters)'),
        ];
        $validator = Validator::make($data, ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email', 'password' => 'required|string|min:12']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        DB::transaction(function () use ($data) {
            $user = new User($data);
            $user->forceFill(['is_active' => true, 'is_system_admin' => true, 'email_verified_at' => now()])->save();
            AuditLog::record($user, $user, 'user.bootstrapped', after: $user->only(['name', 'email', 'is_active', 'is_system_admin']));
        });
        $this->info('System administrator created.');

        return self::SUCCESS;
    }
}
