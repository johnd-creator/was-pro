<?php

namespace App\Http\Middleware;

use App\Http\Requests\SelectOrganizationRequest;
use App\Models\Organization;
use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class UseOrganizationContext
{
    public const ATTRIBUTE = 'active_organization';

    public function __construct(private TenantService $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $request->is('api/*')) {
            return $next($request);
        }

        $operational = $request->routeIs('dashboard', 'waste-management.*');
        $selectsContext = $operational || $request->routeIs('profile.edit');
        $organization = $user->isSuperAdmin() ? null : $user->organization;

        if ($user->isSuperAdmin()) {
            if ($operational && ! $request->isMethodSafe() && ! $request->query->has('organization_id')) {
                abort(409, 'Konteks organisasi tidak ada pada permintaan. Muat ulang halaman lalu coba lagi.');
            }

            $key = 'organization_context.'.$user->id;

            if ($selectsContext && $request->query->has('organization_id')) {
                $id = app(SelectOrganizationRequest::class)->validated('organization_id');
                $organization = $id ? Organization::active()->findOrFail($id) : null;

                if ($organization) {
                    $request->session()->put($key, $organization->id);
                } else {
                    $request->session()->forget($key);
                }
            } elseif ($request->session()->has($key)) {
                $organization = Organization::active()->find($request->session()->get($key));

                if (! $organization) {
                    $request->session()->forget($key);
                }
            } elseif ($organization && ! $organization->is_active) {
                $organization = null;
            }
        } elseif ($selectsContext && $request->query('organization_id') !== null) {
            abort_unless($organization && $request->query('organization_id') === (string) $organization->id, 403);
        }

        $request->attributes->set(self::ATTRIBUTE, $organization);

        if (! $organization && ($operational || (! $user->isSuperAdmin() && $request->routeIs('admin.*')))) {
            if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
                abort($request->routeIs('admin.*') ? 403 : 409, 'Organisasi belum dipilih atau belum ditetapkan pada akun.');
            }

            return Inertia::render('OrganizationRequired')->toResponse($request)
                ->setStatusCode($user->isSuperAdmin() ? 200 : 403);
        }

        if (! $operational) {
            return $next($request);
        }

        $connection = DB::connection();
        $previousPath = $connection->selectOne('SHOW search_path', [], false)->search_path;

        try {
            abort_unless($this->tenants->schemaExists($organization->schema_name), 503, 'Data organisasi belum siap.');
            $this->tenants->switchToSchema($organization->schema_name);

            return $next($request);
        } finally {
            $connection->statement("SELECT set_config('search_path', ?, false)", [$previousPath]);
        }
    }
}
