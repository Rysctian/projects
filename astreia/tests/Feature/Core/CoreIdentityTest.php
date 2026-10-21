<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Models\AccountInvitation;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Project;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ProjectAccess;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

function identityMember(Project $project, User $user, array $keys): void
{
    ProjectAccess::seedCatalog();
    $member = $project->members()->create(['user_id' => $user->id]);
    $role = $project->roles()->create(['name' => 'Member '.$user->id]);
    $role->permissions()->sync(Permission::whereIn('key', $keys)->pluck('id'));
    $member->roles()->attach($role->id, ['project_id' => $project->id]);
}

function identityInvite(): array
{
    Notification::fake();
    $admin = User::factory()->create(['is_system_admin' => true]);
    test()->actingAs($admin)->post('/admin/users', ['name' => 'Invited', 'email' => 'invited@example.test'])->assertSessionHasNoErrors();
    $user = User::where('email', 'invited@example.test')->firstOrFail();
    $token = Notification::sent($user, ResetPassword::class)->first()->token;
    app('auth')->forgetGuards();

    return [$user, $token, $admin];
}

function identityReset(User $user, string $token, string $password = 'A-new-password-123!'): array
{
    return ['email' => $user->email, 'token' => $token, 'password' => $password, 'password_confirmation' => $password];
}

test('global account administration is an explicit grant independent of legacy admin status', function () {
    $operator = User::factory()->create();
    $operator->globalPermissions()->attach(Permission::where('key', 'users.manage')->firstOrFail());
    expect($operator->hasGlobalPermission('users.manage'))->toBeTrue()
        ->and($operator->hasGlobalPermission('projects.create'))->toBeFalse();
    $this->actingAs($operator)->get('/admin/users')->assertOk();

    $admin = User::factory()->create(['is_system_admin' => true]);
    $admin->globalPermissions()->detach();
    $this->actingAs($admin)->get('/admin/users')->assertForbidden();
});

test('administrator creation promotion and demotion maintain explicit global grants', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $target = User::factory()->create();
    expect($admin->hasGlobalPermission('projects.create'))->toBeTrue();
    app(AccountService::class)->updateFlags($admin, $target, ['is_active' => true, 'is_system_admin' => true]);
    expect($target->fresh()->hasGlobalPermission('projects.recover'))->toBeTrue();
    app(AccountService::class)->updateFlags($admin, $target, ['is_active' => true, 'is_system_admin' => false]);
    expect($target->fresh()->hasGlobalPermission('users.manage'))->toBeFalse();
});

test('an account operator cannot promote themselves beyond their global authority', function () {
    $operator = User::factory()->create();
    $operator->globalPermissions()->attach(Permission::where('key', 'users.manage')->firstOrFail());
    $this->actingAs($operator)->patch('/admin/users/'.$operator->id, ['is_active' => true, 'is_system_admin' => true])->assertForbidden();
    expect($operator->fresh()->is_system_admin)->toBeFalse();
});

test('global administrators have legacy bypass but require a core project membership and capability', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $legacy = Project::factory()->create();
    $core = Project::factory()->create(['process_model' => 'core']);
    expect($admin->hasProjectPermission($legacy, 'tickets.view'))->toBeTrue()
        ->and($admin->hasProjectPermission($core, 'project.view'))->toBeFalse();
    identityMember($core, $admin, ['project.view']);
    expect($admin->hasProjectPermission($core, 'project.view'))->toBeTrue()
        ->and($admin->hasProjectPermission($core, 'tickets.edit'))->toBeFalse();
});

test('archived core projects reject administrator writes while permitted reads remain available', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $project = Project::factory()->create(['process_model' => 'core', 'status' => 'archived']);
    identityMember($project, $admin, ['project.view', 'project.manage']);
    ProjectAccess::authorize($admin, $project, 'project.view', false);
    expect(ProjectAccess::capabilities($admin, $project)['project.manage'])->toBeFalse();
    expect(fn () => ProjectAccess::authorize($admin, $project, 'project.manage'))->toThrow(HttpException::class);
});

test('core account suspension cannot remove the final access manager even for a global administrator', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $manager = User::factory()->create();
    $project = Project::factory()->create(['process_model' => 'core']);
    identityMember($project, $manager, ['project.view', 'access.manage', 'members.manage']);
    expect(fn () => app(AccountService::class)->updateFlags($admin, $manager, ['is_active' => false, 'is_system_admin' => false]))->toThrow(ValidationException::class);
    expect($manager->fresh()->is_active)->toBeTrue();
});

test('core membership changes cannot remove the final access manager through administrator bypass', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $project = Project::factory()->create(['process_model' => 'core']);
    identityMember($project, $admin, ['project.view', 'access.manage', 'members.manage']);
    expect(fn () => ProjectAccess::change($admin, $project, 'members.manage', fn () => $project->members()->update(['is_active' => false])))->toThrow(ValidationException::class);
    expect($project->members()->first()->is_active)->toBeTrue();
});

test('explicit recovery restores scoped access with a reason and an audit record', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $target = User::factory()->create();
    $project = Project::factory()->create(['process_model' => 'core']);
    $other = Project::factory()->create(['process_model' => 'core']);
    ProjectAccess::recover($admin, $project, $target, 'Recover after team departure');
    expect($target->hasProjectPermission($project, 'access.manage'))->toBeTrue()
        ->and($target->hasProjectPermission($other, 'project.view'))->toBeFalse()
        ->and($admin->hasProjectPermission($project, 'project.view'))->toBeFalse();
    $audit = AuditLog::where('action', 'project.access_recovered')->firstOrFail();
    expect($audit->actor_id)->toBe($admin->id)
        ->and($audit->after_json['reason'])->toBe('Recover after team departure');
});

test('recovery rejects missing authority empty reasons and archived projects', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $target = User::factory()->create();
    $project = Project::factory()->create(['process_model' => 'core']);
    expect(fn () => ProjectAccess::recover($target, $project, $target, 'Need access'))->toThrow(HttpException::class);
    expect(fn () => ProjectAccess::recover($admin, $project, $target, ' '))->toThrow(ValidationException::class);
    $project->update(['status' => 'archived']);
    expect(fn () => ProjectAccess::recover($admin, $project, $target, 'Need access'))->toThrow(HttpException::class);
    expect($project->members()->count())->toBe(0);
});

test('onboarding persists only a token hash and accepts a setup link once', function () {
    [$user, $token] = identityInvite();
    $invitation = AccountInvitation::where('user_id', $user->id)->firstOrFail();
    expect($invitation->token_hash)->toBe(hash('sha256', $token))
        ->and($invitation->expires_at->isFuture())->toBeTrue();
    $this->post('/reset-password', identityReset($user, $token))->assertSessionHasNoErrors()->assertRedirect('/login');
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
    $password = $user->fresh()->password;
    $this->post('/reset-password', identityReset($user, $token, 'A-different-password-456!'))->assertSessionHasErrors();
    expect($user->fresh()->password)->toBe($password);
});

test('expired invitation state rejects a token even when the broker token has not expired', function () {
    [$user, $token] = identityInvite();
    AccountInvitation::where('user_id', $user->id)->update(['expires_at' => now()->subSecond()]);
    $password = $user->password;
    $this->post('/reset-password', identityReset($user, $token))->assertSessionHasErrors();
    expect($user->fresh()->password)->toBe($password);
});

test('a suspended account cannot request or consume password reset links', function () {
    Notification::fake();
    $user = User::factory()->create();
    $token = Password::createToken($user);
    $user->forceFill(['is_active' => false])->save();
    $password = $user->password;
    $this->post('/reset-password', identityReset($user, $token))->assertSessionHasErrors();
    $this->post('/forgot-password', ['email' => $user->email]);
    Notification::assertNothingSent();
    expect($user->fresh()->password)->toBe($password)->and($user->fresh()->is_active)->toBeFalse();
});

test('accepted invitation cannot overwrite established credentials even before broker token deletion', function () {
    [$user, $token] = identityInvite();
    app(ResetUserPassword::class)->reset($user, identityReset($user, $token));
    $password = $user->fresh()->password;
    expect(fn () => app(ResetUserPassword::class)->reset($user, identityReset($user, $token, 'Other-password-123!')))->toThrow(ValidationException::class);
    expect($user->fresh()->password)->toBe($password);
});

test('setup resends cannot target an established account after its email changes', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $user = User::factory()->create();
    $user->forceFill(['email_verified_at' => null])->save();
    $password = $user->password;
    $this->actingAs($admin)->post('/admin/users/'.$user->id.'/invitation')->assertStatus(422);
    expect($user->fresh()->password)->toBe($password);
});

test('resending setup revokes the earlier invitation and preserves one account', function () {
    [$user, $token, $admin] = identityInvite();
    $this->travel(61)->seconds();
    $this->actingAs($admin)->post('/admin/users/'.$user->id.'/invitation')->assertSessionHasNoErrors();
    $latest = Notification::sent($user, ResetPassword::class)->last()->token;
    expect($latest)->not->toBe($token)
        ->and(User::where('email', $user->email)->count())->toBe(1)
        ->and(AccountInvitation::where('token_hash', hash('sha256', $token))->firstOrFail()->revoked_at)->not->toBeNull();
    $this->app['auth']->forgetGuards();
    $this->post('/reset-password', identityReset($user, $token))->assertSessionHasErrors();
    $this->post('/reset-password', identityReset($user, $latest))->assertSessionHasNoErrors();
});

test('ordinary password recovery still works after invitation acceptance', function () {
    [$user, $token] = identityInvite();
    $this->post('/reset-password', identityReset($user, $token))->assertSessionHasNoErrors();
    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
    $reset = Notification::sent($user, ResetPassword::class)->last()->token;
    $this->post('/reset-password', identityReset($user, $reset, 'Recovered-password-123!'))->assertSessionHasNoErrors();
    expect(Hash::check('Recovered-password-123!', $user->fresh()->password))->toBeTrue();
});

test('shared account management capability follows explicit grants', function () {
    $operator = User::factory()->create();
    $operator->globalPermissions()->attach(Permission::where('key', 'users.manage')->firstOrFail());
    $this->actingAs($operator)->get('/projects')->assertInertia(fn ($page) => $page->where('auth.canManageUsers', true));
    $operator->globalPermissions()->detach();
    $this->get('/projects')->assertInertia(fn ($page) => $page->where('auth.canManageUsers', false));
});

test('archive restoration capability requires scoped management and exposes no write bypass', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $project = Project::factory()->create(['process_model' => 'core', 'status' => 'archived']);
    expect(ProjectAccess::capabilities($admin, $project)['project.restore'] ?? false)->toBeFalse();
    identityMember($project, $admin, ['project.view', 'project.manage']);
    expect(ProjectAccess::capabilities($admin, $project)['project.restore'] ?? false)->toBeTrue()
        ->and(ProjectAccess::capabilities($admin, $project)['project.manage'])->toBeFalse();
});

test('administrative audits record correlation occurrence and recording timestamps', function () {
    $actor = User::factory()->create();
    $audit = AuditLog::record($actor, $actor, 'test.audit');
    $audit->refresh();
    expect($audit->correlation_id)->not->toBeNull()
        ->and($audit->occurred_at)->not->toBeNull()
        ->and($audit->recorded_at)->not->toBeNull();
});

test('the final explicit account administrator is protected without a legacy admin flag', function () {
    $operator = User::factory()->create();
    $operator->globalPermissions()->attach(Permission::where('key', 'users.manage')->firstOrFail());
    expect(fn () => app(AccountService::class)->updateFlags($operator, $operator, ['is_active' => false, 'is_system_admin' => false]))->toThrow(ValidationException::class);
    expect($operator->fresh()->is_active)->toBeTrue();
});

test('legacy administrator demotion is allowed when another explicit account administrator remains', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $operator = User::factory()->create();
    $operator->globalPermissions()->attach(Permission::where('key', 'users.manage')->firstOrFail());
    app(AccountService::class)->updateFlags($admin, $admin, ['is_active' => true, 'is_system_admin' => false]);
    expect($admin->fresh()->hasGlobalPermission('users.manage'))->toBeFalse()
        ->and($operator->fresh()->hasGlobalPermission('users.manage'))->toBeTrue();
});

test('pending setup does not count as a usable replacement access manager', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $manager = User::factory()->create();
    $pending = User::factory()->create(['password_setup_required' => true]);
    $project = Project::factory()->create(['process_model' => 'core']);
    identityMember($project, $manager, ['project.view', 'access.manage', 'members.manage']);
    identityMember($project, $pending, ['project.view', 'access.manage', 'members.manage']);
    expect(fn () => app(AccountService::class)->updateFlags($admin, $manager, ['is_active' => false, 'is_system_admin' => false]))->toThrow(ValidationException::class);
});

test('pending setup does not count as a usable replacement account administrator', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    User::factory()->create(['is_system_admin' => true, 'password_setup_required' => true]);
    expect(fn () => app(AccountService::class)->updateFlags($admin, $admin, ['is_active' => false, 'is_system_admin' => true]))->toThrow(ValidationException::class);
});

test('public password recovery cannot replace an unaccepted invitation', function () {
    [$user, $token] = identityInvite();
    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasErrors();
    $this->post('/reset-password', identityReset($user, $token))->assertSessionHasNoErrors();
});

test('bootstrap grants explicit global administration', function () {
    $this->artisan('astreia:bootstrap-admin', ['--name' => 'Owner', '--email' => 'owner@example.test'])
        ->expectsQuestion('Password (minimum 12 characters)', 'A-strong-password-123')->assertSuccessful();
    $owner = User::where('email', 'owner@example.test')->firstOrFail();
    foreach (['users.manage', 'projects.create', 'projects.recover'] as $key) {
        expect($owner->hasGlobalPermission($key))->toBeTrue();
    }
});

test('identity migration backfills only existing administrators without changing credentials', function () {
    $migration = require database_path('migrations/2026_10_21_000200_core_identity.php');
    $migration->down();
    $admin = User::withoutEvents(fn () => User::factory()->create(['is_system_admin' => true]));
    $member = User::factory()->create();
    $password = $admin->password;
    $migration->up();
    expect($admin->fresh()->hasGlobalPermission('users.manage'))->toBeTrue()
        ->and($admin->fresh()->hasGlobalPermission('projects.create'))->toBeTrue()
        ->and($admin->fresh()->hasGlobalPermission('projects.recover'))->toBeTrue()
        ->and($member->fresh()->hasGlobalPermission('users.manage'))->toBeFalse()
        ->and($admin->fresh()->password)->toBe($password)
        ->and($admin->fresh()->password_setup_required)->toBeFalse();
});

test('failed invitation delivery can be retried without duplicate accounts or credential changes', function () {
    config(['mail.default' => 'missing-transport']);
    $admin = User::factory()->create(['is_system_admin' => true]);
    $this->actingAs($admin)->post('/admin/users', ['name' => 'Pending', 'email' => 'pending@example.test'])->assertSessionHasErrors('invitation');
    $user = User::where('email', 'pending@example.test')->firstOrFail();
    $password = $user->password;
    $first = $user->invitations()->firstOrFail();
    $this->travel(61)->seconds();
    Notification::fake();
    $this->post('/admin/users/'.$user->id.'/invitation')->assertSessionHasNoErrors();
    expect(User::where('email', $user->email)->count())->toBe(1)
        ->and($user->fresh()->password)->toBe($password)
        ->and($first->fresh()->revoked_at)->not->toBeNull();
    $token = Notification::sent($user, ResetPassword::class)->last()->token;
    $this->app['auth']->forgetGuards();
    $this->post('/reset-password', identityReset($user, $token))->assertSessionHasNoErrors();
});
