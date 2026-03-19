<?php

namespace App\Http\Middleware;

use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsSuperAdmin
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
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->authorizationService->isSuperAdmin()) {
            abort(403, 'This action is unauthorized.');
        }

        return $next($request);
    }
}
