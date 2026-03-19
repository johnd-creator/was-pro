<?php

namespace App\Http\Middleware;

use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HasRole
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected AuthorizationService $authorizationService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (! $this->authorizationService->hasRole($role)) {
            abort(403, 'You do not have the required role to access this resource.');
        }

        return $next($request);
    }
}
