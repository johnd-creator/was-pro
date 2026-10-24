<?php

namespace App\Http\Middleware;

use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetTenantSchema
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        protected TenantService $tenantService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only set tenant schema if user is authenticated and has an organization
        if (Auth::check() && Auth::user()->organization_id) {
            $organization = Auth::user()->organization;

            if (! $organization || ! $this->tenantService->schemaExists($organization->schema_name)) {
                $this->tenantService->switchToPublic();

                if ($request->is('api/*')) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data organisasi belum siap. Hubungi administrator.',
                        'error_code' => 'TENANT_NOT_READY',
                        'errors' => [],
                    ], 503);
                }

                abort(503, 'Data organisasi belum siap. Hubungi administrator.');
            }

            $this->tenantService->switchToSchema($organization->schema_name);
        } else {
            // Switch to public schema for non-authenticated users or super admins without organization
            $this->tenantService->switchToPublic();
        }

        return $next($request);
    }
}
