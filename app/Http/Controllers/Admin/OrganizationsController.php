<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrganizationRequest;
use App\Models\Organization;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OrganizationsController extends Controller
{
    public function __construct(
        protected TenantService $tenantService
    ) {}

    /**
     * Display a listing of organizations.
     */
    public function index(): Response
    {
        $organizations = Organization::orderBy('name')->get();

        return Inertia::render('admin/Organizations', [
            'organizations' => $organizations,
        ]);
    }

    /**
     * Store a newly created organization.
     */
    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        try {
            $this->tenantService->provisionOrganization([
                ...$request->validated(),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return Redirect::back()->withInput()
                ->with('error', 'Organisasi belum dapat disiapkan. Silakan coba lagi atau hubungi administrator.');
        }

        return Redirect::route('admin.organizations.index')
            ->with('success', 'Organisasi berhasil dibuat dan disiapkan.');
    }

    /**
     * Update the specified organization.
     */
    public function update(Organization $organization): RedirectResponse
    {
        $validated = request()->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:organizations,code,'.$organization->id,
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
        ]);

        $organization->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'description' => $validated['description'] ?? null,
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'updated_by' => auth()->id(),
        ]);

        return Redirect::route('admin.organizations.index')
            ->with('success', 'Organization updated successfully.');
    }

    /**
     * Remove the specified organization.
     */
    public function destroy(Organization $organization): RedirectResponse
    {
        // Check if organization has users
        if ($organization->users()->count() > 0) {
            return Redirect::back()
                ->with('error', 'Cannot delete organization with users. Please reassign or delete users first.');
        }

        $schemaName = $organization->schema_name;

        $organization->delete();

        // Drop the schema
        $this->tenantService->dropSchema($schemaName);

        return Redirect::route('admin.organizations.index')
            ->with('success', 'Organization deleted successfully.');
    }
}
