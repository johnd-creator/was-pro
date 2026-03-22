<?php

namespace App\Http\Middleware;

use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HasPermission
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
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $permissions = explode('|', $permission);
        $hasAnyPermission = collect($permissions)
            ->contains(fn (string $permissionSlug): bool => $this->authorizationService->hasPermission(trim($permissionSlug)));

        if (! $hasAnyPermission) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
