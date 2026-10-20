<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

test('public registration is unavailable', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

test('inactive users cannot authenticate or retain an existing session', function () {
    $user = User::factory()->create(['is_active' => false]);
    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->assertGuest();
    $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

test('only system administrators may invite accounts with expiring password setup', function () {
    Notification::fake();
    $member = User::factory()->create();
    $this->actingAs($member)->post('/admin/users', ['name' => 'Alex', 'email' => 'alex@example.test'])->assertForbidden();
    $admin = User::factory()->create(['is_system_admin' => true]);
    $this->actingAs($admin)->post('/admin/users', ['name' => 'Alex', 'email' => 'alex@example.test'])->assertRedirect();
    $invited = User::where('email', 'alex@example.test')->firstOrFail();
    expect($invited->is_active)->toBeTrue()->and($invited->is_system_admin)->toBeFalse();
    Notification::assertSentTo($invited, ResetPassword::class);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.created', 'actor_id' => $admin->id]);
});

test('the last active system administrator cannot be deactivated or demoted', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $this->actingAs($admin)->patch('/admin/users/'.$admin->id, ['is_active' => false, 'is_system_admin' => false])->assertSessionHasErrors();
    expect($admin->fresh()->is_active)->toBeTrue()->and($admin->fresh()->is_system_admin)->toBeTrue();
});

test('bootstrap command creates one administrator and does not replace existing accounts', function () {
    $this->artisan('astreia:bootstrap-admin', ['--name' => 'Owner', '--email' => 'owner@example.test'])
        ->expectsQuestion('Password (minimum 12 characters)', 'A-strong-password-123')
        ->assertSuccessful();
    expect(User::where('email', 'owner@example.test')->firstOrFail()->is_system_admin)->toBeTrue();
    $this->artisan('astreia:bootstrap-admin', ['--name' => 'Other', '--email' => 'other@example.test'])->assertFailed();
});

test('last administrator cannot bypass protection through profile deactivation', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $this->actingAs($admin)->delete('/settings/profile', ['password' => 'password'])->assertSessionHasErrors();
    expect($admin->fresh()->is_active)->toBeTrue();
});

test('newly invited users verify their email when setting their first password', function () {
    $user = User::factory()->unverified()->create();
    $token = Password::createToken($user);
    $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'password', 'password_confirmation' => 'password'])->assertRedirect('/login');
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('administrators can resend password setup without exposing tokens', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_system_admin' => true]);
    $user = User::factory()->unverified()->create();
    $this->actingAs($admin)->post('/admin/users/'.$user->id.'/invitation')->assertRedirect()->assertSessionHasNoErrors();
    Notification::assertSentTo($user, ResetPassword::class);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.invitation_sent', 'entity_id' => $user->id]);
});

test('failed mail delivery preserves the account and provides a retry path', function () {
    config(['mail.default' => 'missing-mail-transport']);
    $admin = User::factory()->create(['is_system_admin' => true]);
    $this->actingAs($admin)->post('/admin/users', ['name' => 'Invited', 'email' => 'invited@example.test'])->assertSessionHasErrors('invitation');
    $this->assertDatabaseHas('users', ['email' => 'invited@example.test', 'is_active' => true]);
});
