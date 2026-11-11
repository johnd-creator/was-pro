<?php

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

uses(Tests\TestCase::class, DatabaseMigrations::class);

function authenticationWait(callable $ready, string $message): void
{
    $deadline = microtime(true) + 12;
    while (! $ready()) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException($message);
        }
        usleep(10000);
    }
}

function authenticationWorker(string $directory, string $name, array $input): Process
{
    $path = $directory.'/'.$name.'.json';
    file_put_contents($path, json_encode($input, JSON_THROW_ON_ERROR));
    $process = new Process([PHP_BINARY, base_path('tests/Fixtures/authentication-worker.php'), $path], base_path(), timeout: 35);
    $process->start();

    return $process;
}

function authenticationResult(Process $worker): array
{
    $worker->wait();
    expect($worker->getExitCode())->toBe(0, $worker->getErrorOutput().$worker->getOutput());

    return json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
}

test('API login and password reset serialize both lock orderings', function (bool $resetFirst) {
    $user = User::factory()->create();
    $directory = storage_path('framework/testing/auth-race-'.Str::uuid());
    mkdir($directory, 0777, true);
    $key = random_int(100000, 2000000000);
    $loginName = 'auth_login_'.Str::random(10);
    $resetName = 'auth_reset_'.Str::random(10);
    $workers = [];
    $gateHeld = false;
    $loginInput = ['mode' => 'login', 'application' => $loginName, 'email' => $user->email];
    $resetInput = ['mode' => 'reset', 'application' => $resetName, 'user' => $user->id];
    try {
        if ($resetFirst) {
            $reset = authenticationWorker($directory, 'reset', [...$resetInput, 'ready' => $directory.'/ready', 'release' => $directory.'/release']);
            $workers[] = $reset;
            authenticationWait(fn () => file_exists($directory.'/ready'), 'Reset did not hold its transaction.');
            $login = authenticationWorker($directory, 'login', $loginInput);
            $workers[] = $login;
            authenticationWait(fn () => DB::selectOne("SELECT count(*) AS total FROM pg_stat_activity WHERE application_name = ? AND wait_event_type = 'Lock'", [$loginName])->total > 0, 'Login did not wait for the password row lock.');
            touch($directory.'/release');
        } else {
            DB::select('SELECT pg_advisory_lock(?)', [$key]);
            $gateHeld = true;
            DB::unprepared("CREATE FUNCTION public.auth_token_delay() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN PERFORM pg_advisory_xact_lock({$key}); RETURN NEW; END $$; CREATE TRIGGER auth_token_delay BEFORE INSERT ON public.api_tokens FOR EACH ROW EXECUTE FUNCTION public.auth_token_delay()");
            $login = authenticationWorker($directory, 'login', $loginInput);
            $workers[] = $login;
            authenticationWait(fn () => DB::selectOne("SELECT count(*) AS total FROM pg_stat_activity WHERE application_name = ? AND wait_event = 'advisory'", [$loginName])->total > 0, 'Login did not reach token insertion.');
            $reset = authenticationWorker($directory, 'reset', $resetInput);
            $workers[] = $reset;
            authenticationWait(fn () => DB::selectOne("SELECT count(*) AS total FROM pg_stat_activity WHERE application_name = ? AND wait_event_type = 'Lock'", [$resetName])->total > 0, 'Reset did not wait for the login row lock.');
            DB::select('SELECT pg_advisory_unlock(?)', [$key]);
            $gateHeld = false;
        }
        $loginResult = authenticationResult($login);
        expect(authenticationResult($reset)['status'])->toBe(200)
            ->and($loginResult['status'])->toBe($resetFirst ? 422 : 200)
            ->and(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue()
            ->and(ApiToken::where('user_id', $user->id)->count())->toBe(0);
        if ($resetFirst) {
            expect($loginResult['body']['error_code'])->toBe('INVALID_CREDENTIALS');
        }
    } finally {
        if ($gateHeld) {
            DB::select('SELECT pg_advisory_unlock(?)', [$key]);
        }
        touch($directory.'/release');
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        DB::unprepared('DROP TRIGGER IF EXISTS auth_token_delay ON public.api_tokens; DROP FUNCTION IF EXISTS public.auth_token_delay()');
        foreach (glob($directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
})->with(['reset first' => true, 'login first' => false]);
