<?php

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

function authBoundaryToken(User $user, string $plain): ApiToken
{
    return $user->apiTokens()->create([
        'name' => 'boundary-test',
        'token' => hash('sha256', $plain),
        'expires_at' => now()->addDays(30),
    ]);
}

test('mobile authentication follows Fortify MFA enrollment semantics without deleting tokens', function (bool $secret, bool $confirmed, bool $confirmRequired, bool $blocked) {
    config(['fortify-options.two-factor-authentication.confirm' => $confirmRequired]);
    $user = User::factory()->create();
    $user->forceFill([
        'two_factor_secret' => $secret ? encrypt('synthetic-test-secret') : null,
        'two_factor_confirmed_at' => $confirmed ? now() : null,
    ])->save();
    $existing = authBoundaryToken($user, 'existing-boundary-token');
    $existing->forceFill(['created_at' => now()->subDays(2)])->save();
    $newer = authBoundaryToken($user, 'newer-boundary-token');

    $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password']);
    if ($blocked) {
        $login->assertForbidden()->assertJsonPath('error_code', 'MFA_WEB_LOGIN_REQUIRED')->assertJsonMissingPath('data.token');
        expect($user->apiTokens()->count())->toBe(2);
        foreach (['existing-boundary-token', 'newer-boundary-token'] as $plain) {
            $this->withToken($plain)->getJson('/api/v1/auth/me')
                ->assertUnauthorized()->assertJsonPath('error_code', 'MFA_WEB_LOGIN_REQUIRED');
        }
        expect($existing->fresh()->last_used_at)->toBeNull()
            ->and($newer->fresh()->last_used_at)->toBeNull()
            ->and($user->apiTokens()->count())->toBe(2);
        $this->assertGuest();
    } else {
        $login->assertSuccessful()->assertJsonPath('data.token_type', 'Bearer');
        $this->withToken($login->json('data.token'))->getJson('/api/v1/auth/me')->assertSuccessful();
        $this->withToken('existing-boundary-token')->getJson('/api/v1/auth/me')->assertSuccessful();
    }
})->with([
    'no MFA' => [false, false, true, false],
    'confirmed MFA' => [true, true, true, true],
    'unconfirmed enrollment' => [true, false, true, false],
    'confirmation not required' => [true, false, false, true],
]);

test('MFA account still requires a valid password before reporting mobile limitation', function () {
    $user = User::factory()->create();
    $user->forceFill(['two_factor_secret' => encrypt('synthetic'), 'two_factor_confirmed_at' => now()])->save();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertUnprocessable()->assertJsonPath('error_code', 'INVALID_CREDENTIALS');
    expect($user->apiTokens()->count())->toBe(0);
});

test('password actions revoke only target tokens atomically', function (string $channel, bool $failure) {
    $user = User::factory()->create(['is_super_admin' => true, 'organization_id' => null]);
    $other = User::factory()->create(['is_super_admin' => true, 'organization_id' => null]);
    authBoundaryToken($user, 'target-token-one');
    authBoundaryToken($user, 'target-token-two');
    $otherToken = authBoundaryToken($other, 'other-token');
    $resetToken = $channel === 'recovery' ? Password::broker()->createToken($user) : null;

    if ($failure) {
        DB::unprepared("CREATE FUNCTION public.boundary_revocation_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'synthetic revocation failure'; END $$; CREATE TRIGGER boundary_revocation_failure BEFORE DELETE ON public.api_tokens FOR EACH ROW EXECUTE FUNCTION public.boundary_revocation_failure()");
    }

    $payload = ['password' => 'new-password-123', 'password_confirmation' => 'new-password-123'];
    $response = match ($channel) {
        'settings' => $this->actingAs($user)->put(route('user-password.update'), [...$payload, 'current_password' => 'password']),
        'admin' => $this->actingAs($other)->put(route('admin.users.update-password', $user), $payload),
        'recovery' => $this->post(route('password.update'), [...$payload, 'email' => $user->email, 'token' => $resetToken]),
    };

    if ($failure) {
        $response->assertServerError();
        expect(Hash::check('password', $user->fresh()->password))->toBeTrue()
            ->and($user->apiTokens()->count())->toBe(2);
    } else {
        $response->assertRedirect()->assertSessionHasNoErrors();
        expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue()
            ->and($user->apiTokens()->count())->toBe(0);
    }
    expect($otherToken->fresh())->not->toBeNull();
})->with(['settings', 'admin', 'recovery'])->with([false, true]);
