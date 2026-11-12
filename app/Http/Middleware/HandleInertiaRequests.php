<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // Prepare user data for frontend with permissions
        $userData = null;
        if ($user) {
            $user->load('role.permissions');

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => $user->is_super_admin,
                'organization_id' => $user->organization_id,
                'role_id' => $user->role_id,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'permissions' => $user->is_super_admin
                    ? \App\Models\Permission::pluck('slug')->toArray()
                    : $user->role?->permissions->pluck('slug')->toArray() ?? [],
            ];
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'notificationSummary' => fn (): array => app(\App\Services\OperationalAlerts::class)->forRequest($request)['summary'],
            'notificationContext' => fn (): array => app(\App\Services\OperationalAlerts::class)->forRequest($request)['context'],
            'operationalAlerts' => fn (): array => app(\App\Services\OperationalAlerts::class)->forRequest($request)['alerts'],
            'hauling_plans_available' => fn (): bool => app(\App\Services\HaulingPlans::class)->enabled(),
            'hauling_plans_enabled' => fn (): bool => app(\App\Services\HaulingPlans::class)->enabled(),
            'operational_inbox_available' => fn (): bool => app(\App\Services\OperationalInbox::class)->available(),
            'auth' => [
                'user' => $userData,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'organizationContext' => fn (): array => [
                'organization' => ($organization = $request->attributes->get(UseOrganizationContext::ATTRIBUTE))
                    ? ['id' => $organization->id, 'name' => $organization->name, 'code' => $organization->code]
                    : null,
                'can_select' => $user?->isSuperAdmin() ?? false,
                'is_global' => ($user?->isSuperAdmin() ?? false) && $request->routeIs('admin.*'),
                'choices' => $user?->isSuperAdmin()
                    ? \App\Models\Organization::active()->orderBy('name')->get(['id', 'name', 'code'])->toArray()
                    : [],
            ],
        ];
    }
}
