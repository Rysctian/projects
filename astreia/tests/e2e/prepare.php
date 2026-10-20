<?php

use App\Models\User;
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
}
