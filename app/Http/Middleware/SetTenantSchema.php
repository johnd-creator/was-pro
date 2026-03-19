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

            if ($organization) {
                $schemaName = $organization->schema_name;

                // Ensure the schema exists before switching
                if (! $this->tenantService->schemaExists($schemaName)) {
                    // Create the schema if it doesn't exist
                    $this->tenantService->createSchema($schemaName);
                }

                // Switch to the organization's schema
                $this->tenantService->switchToSchema($schemaName);
            }
        } else {
            // Switch to public schema for non-authenticated users or super admins without organization
            $this->tenantService->switchToPublic();
        }

        return $next($request);
    }
}
