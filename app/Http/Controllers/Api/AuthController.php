<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Auth\LoginRequest;
use App\Models\ApiToken;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends ApiController
{
    public function login(LoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()
            ->with(['organization', 'role.permissions'])
            ->where('email', $request->validated('email'))
            ->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return $this->error('Email atau password tidak valid.', 'INVALID_CREDENTIALS', status: 422);
        }

        $plainTextToken = Str::random(80);
        $token = ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => $request->validated('device_name') ?: 'flutter-mobile',
            'token' => hash('sha256', $plainTextToken),
            'last_used_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        return $this->success([
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->expires_at?->toIso8601String(),
            'context' => $this->serializeUserContext($user),
        ], 'Login berhasil.');
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('current_api_token');

        if ($token instanceof ApiToken) {
            $token->delete();
        }

        return $this->success(message: 'Logout berhasil.');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->loadMissing(['organization', 'role.permissions']);

        return $this->success($this->serializeUserContext($user));
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeUserContext(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => $user->is_super_admin,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ],
            'organization' => $user->organization ? [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'code' => $user->organization->code,
            ] : null,
            'role' => $user->role ? [
                'id' => $user->role->id,
                'name' => $user->role->name,
                'slug' => $user->role->slug,
            ] : null,
            'permissions' => $user->isSuperAdmin()
                ? Permission::query()->pluck('slug')->all()
                : $user->role?->permissions->pluck('slug')->values()->all() ?? [],
        ];
    }
}
