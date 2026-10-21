<?php

use App\Models\Permission;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAccess;
use App\Services\WorkflowService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('database.default') !== 'sqlite' || ! preg_match('/astreia-e2e-\d+\.sqlite$/', config('database.connections.sqlite.database'))) {
    throw new RuntimeException('Refusing to seed browser fixtures into an application database.');
}

foreach ([['name' => 'Core Admin', 'email' => 'admin@astreia.test', 'is_system_admin' => true], ['name' => 'Core Developer', 'email' => 'developer@astreia.test', 'is_system_admin' => false]] as $data) {
    $user = new User;
    $user->forceFill([...$data, 'password' => 'browser-test-password', 'email_verified_at' => now(), 'is_active' => true])->save();
    if ($user->is_system_admin) {
        $user->globalPermissions()->sync(Permission::where('scope', 'global')->pluck('id'));
    }
}

$admin = User::where('email', 'admin@astreia.test')->firstOrFail();
foreach (['E2ECORE', 'E2EFAST', 'E2EUILEG', 'E2ESETLEG'] as $code) {
    $project = Project::factory()->create(['code' => $code, 'name' => $code.' legacy workspace', 'created_by' => $admin->id]);
    ProjectAccess::templates($project);
    app(WorkflowService::class)->initialize($project, $admin);
}
