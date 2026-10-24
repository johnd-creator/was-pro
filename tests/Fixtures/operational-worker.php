<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
Tests\Support\DisposableDatabase::verify($app);
$input = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
touch($input['ready']);
$deadline = microtime(true) + 15;
while (! file_exists($input['go'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Worker start barrier timed out.');
    }
    usleep(10000);
}
$request = Illuminate\Http\Request::create($input['url'], $input['method'] ?? 'POST', [], [], [], [
    'HTTP_AUTHORIZATION' => 'Bearer '.($input['token'] ?? ''), 'HTTP_ACCEPT' => isset($input['web_actor']) ? 'text/html' : 'application/json', 'CONTENT_TYPE' => 'application/json',
], json_encode($input['body'] ?? []));
if (isset($input['idempotency_key'])) {
    $request->headers->set('Idempotency-Key', $input['idempotency_key']);
}
if (isset($input['web_actor'])) {
    $app->instance('request', $request);
    Illuminate\Support\Facades\Auth::guard('web')->loginUsingId($input['web_actor']);
}
$started = microtime(true);
$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode(), 'body' => $response->getContent(), 'started' => $started, 'finished' => microtime(true), 'replayed' => $response->headers->get('Idempotency-Replayed'), 'error' => $request->hasSession() ? $request->session()->get('error') : null], JSON_THROW_ON_ERROR);
$kernel->terminate($request, $response);
