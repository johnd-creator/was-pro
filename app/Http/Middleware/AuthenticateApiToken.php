<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();

        if (! $plainTextToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token autentikasi tidak ditemukan.',
                'error_code' => 'UNAUTHENTICATED',
                'errors' => [],
            ], 401);
        }

        $token = ApiToken::query()
            ->with(['user.organization', 'user.role.permissions'])
            ->where('token', hash('sha256', $plainTextToken))
            ->first();

        if (! $token || ! $token->user) {
            return response()->json([
                'success' => false,
                'message' => 'Token autentikasi tidak valid.',
                'error_code' => 'UNAUTHENTICATED',
                'errors' => [],
            ], 401);
        }

        if ($token->isExpired()) {
            $token->delete();

            return response()->json([
                'success' => false,
                'message' => 'Token autentikasi telah kedaluwarsa.',
                'error_code' => 'UNAUTHENTICATED',
                'errors' => [],
            ], 401);
        }

        $token->forceFill([
            'last_used_at' => now(),
        ])->save();

        Auth::setUser($token->user);
        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('current_api_token', $token);

        return $next($request);
    }
}
