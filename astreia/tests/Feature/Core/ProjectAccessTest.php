<?php

use App\Models\Permission;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function coreProject(User $admin, string $code = 'HRIS'): Project
{
    return legacyProjectFixture($admin, $code);
}

function coreMember(Project $project, User $user, array $keys): void
{
    ProjectAccess::seedCatalog();
    $member = $project->members()->create(['user_id' => $user->id]);
    $role = $project->roles()->create(['name' => 'Custom '.$user->id]);
    $role->permissions()->sync(Permission::whereIn('key', $keys)->pluck('id'));
    $member->roles()->attach($role->id, ['project_id' => $project->id]);
}

test('projects are visible only by explicit project permission or system administration', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $project = coreProject($admin);
    $other = coreProject($admin, 'CRM');
    $user = User::factory()->create();
    coreMember($project, $user, ['project.view']);
    $this->actingAs($user)->get('/projects')->assertOk()->assertInertia(fn ($page) => $page->has('projects.data', 1)->where('projects.data.0.code', 'HRIS'));
    $this->get('/projects/'.$other->id)->assertForbidden();
    $this->post('/projects', ['code' => 'EVIL', 'name' => 'No'])->assertForbidden();
    $this->get('/projects/'.$project->id)->assertOk();
});

test('role names give no authority and explicit deny overrides combined grants', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $project = coreProject($admin);
    $user = User::factory()->create();
    coreMember($project, $user, ['project.view', 'tickets.edit']);
    $member = $project->members()->where('user_id', $user->id)->firstOrFail();
    $permission = Permission::where('key', 'tickets.edit')->firstOrFail();
    $member->overrides()->create(['permission_id' => $permission->id, 'effect' => 'deny']);
    expect($user->hasProjectPermission($project, 'tickets.edit'))->toBeFalse();
    expect($user->hasProjectPermission($project, 'project.view'))->toBeTrue();
    $member->roles()->first()->update(['name' => 'System Admin']);
    expect($user->hasProjectPermission($project, 'access.manage'))->toBeFalse();
    expect($admin->hasProjectPermission($project, 'access.manage'))->toBeTrue();
});

test('access managers cannot grant capabilities they lack or assign another project role', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $project = coreProject($admin);
    $other = coreProject($admin, 'CRM');
    $manager = User::factory()->create();
    $target = User::factory()->create();
    coreMember($project, $manager, ['project.view', 'access.manage', 'members.manage']);
    $this->actingAs($manager)->post('/projects/'.$project->id.'/roles', ['name' => 'Elevated', 'permissions' => ['tickets.close']])->assertForbidden();
    $foreignRole = $other->roles()->create(['name' => 'Foreign']);
    $this->post('/projects/'.$project->id.'/members', ['user_id' => $target->id, 'role_ids' => [$foreignRole->id]])->assertSessionHasErrors('role_ids.0');
    $this->post('/projects/'.$project->id.'/roles', ['name' => 'Implementation Reviewer', 'permissions' => ['project.view']])->assertRedirect();
    $this->assertDatabaseHas('roles', ['name' => 'Implementation Reviewer', 'project_id' => $project->id]);
});

test('last project access manager and archived projects are protected', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $project = coreProject($admin);
    $manager = User::factory()->create();
    coreMember($project, $manager, ['project.view', 'access.manage', 'members.manage', 'project.manage']);
    $member = $project->members()->where('user_id', $manager->id)->firstOrFail();
    $this->actingAs($manager)->patch('/projects/'.$project->id.'/members/'.$member->id, ['role_ids' => [], 'is_active' => false])->assertSessionHasErrors();
    $project->update(['status' => 'archived', 'archived_at' => now()]);
    $this->patch('/projects/'.$project->id, ['name' => 'Changed', 'status' => 'active'])->assertForbidden();
    $this->get('/projects/'.$project->id)->assertOk();
});

test('membership reactivation cannot restore override permissions the manager lacks', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $project = coreProject($admin);
    $manager = User::factory()->create();
    $target = User::factory()->create();
    coreMember($project, $manager, ['project.view', 'access.manage', 'members.manage']);
    $member = $project->members()->create(['user_id' => $target->id, 'is_active' => false]);
    $member->overrides()->create(['permission_id' => Permission::where('key', 'tickets.close')->firstOrFail()->id, 'effect' => 'allow']);
    $this->actingAs($manager)->post('/projects/'.$project->id.'/members', ['user_id' => $target->id, 'role_ids' => []])->assertForbidden();
    $this->patch('/projects/'.$project->id.'/members/'.$member->id, ['role_ids' => [], 'is_active' => true])->assertForbidden();
    expect($member->fresh()->is_active)->toBeFalse();
});

test('the final access manager cannot deny their own project visibility', function () {
    $admin = User::factory()->create(['is_system_admin' => true]);
    $project = coreProject($admin);
    $manager = User::factory()->create();
    coreMember($project, $manager, ['project.view', 'access.manage']);
    $member = $project->members()->where('user_id', $manager->id)->firstOrFail();
    $this->actingAs($manager)->put('/projects/'.$project->id.'/members/'.$member->id.'/overrides', ['overrides' => [['key' => 'project.view', 'effect' => 'deny']]])->assertSessionHasErrors('access');
    expect($manager->hasProjectPermission($project, 'project.view'))->toBeTrue();
});
