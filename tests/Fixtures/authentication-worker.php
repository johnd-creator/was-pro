<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
Tests\Support\DisposableDatabase::verify($app);
$input = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
Illuminate\Support\Facades\DB::statement("SELECT set_config('application_name', ?, false)", [$input['application']]);
Illuminate\Support\Facades\DB::statement("SET lock_timeout = '15s'");
if ($input['mode'] === 'reset') {
    $user = App\Models\User::findOrFail($input['user']);
    $user->getConnection()->transaction(function () use ($user, $input): void {
        app(App\Actions\Fortify\ResetUserPassword::class)->reset($user, [
            'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ]);
        if (isset($input['ready'])) {
            touch($input['ready']);
            $deadline = microtime(true) + 15;
            while (! file_exists($input['release'])) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Reset release barrier timed out.');
                }
                usleep(10000);
            }
        }
    });
    echo json_encode(['status' => 200]);
} else {
    $request = Illuminate\Http\Request::create('/api/v1/auth/login', 'POST', [], [], [], [
        'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
    ], json_encode(['email' => $input['email'], 'password' => 'password']));
    $response = $kernel->handle($request);
    echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)], JSON_THROW_ON_ERROR);
    $kernel->terminate($request, $response);
}
