<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('correlation_id')->nullable();
            $table->dateTime('occurred_at', 6)->nullable();
            $table->dateTime('recorded_at', 6)->nullable();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('password_setup_required')->default(false);
        });
        Schema::create('global_user_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->primary(['user_id', 'permission_id']);
        });
        Schema::create('account_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at', 6);
            $table->dateTime('accepted_at', 6)->nullable();
            $table->dateTime('revoked_at', 6)->nullable();
            $table->timestamps(6);
        });

        foreach (['users.manage', 'projects.create', 'projects.recover'] as $key) {
            DB::table('permissions')->insertOrIgnore([
                'key' => $key, 'scope' => 'global', 'description' => ucfirst(str_replace('.', ' ', $key)),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $permissionId = DB::table('permissions')->where('key', $key)->value('id');
            DB::table('users')->where('is_system_admin', true)->orderBy('id')->chunkById(200, function ($users) use ($permissionId) {
                DB::table('global_user_permissions')->insertOrIgnore($users->map(fn ($user) => [
                    'user_id' => $user->id, 'permission_id' => $permissionId,
                ])->all());
            });
        }
    }

    public function down(): void
    {
        Schema::table('audit_logs', fn (Blueprint $table) => $table->dropColumn(['correlation_id', 'occurred_at', 'recorded_at']));
        Schema::dropIfExists('account_invitations');
        Schema::dropIfExists('global_user_permissions');
        DB::table('permissions')->where('scope', 'global')->whereIn('key', ['users.manage', 'projects.create', 'projects.recover'])->delete();
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('password_setup_required'));
    }
};
