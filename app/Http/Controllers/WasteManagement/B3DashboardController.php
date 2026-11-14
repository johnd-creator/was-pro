<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Middleware\UseOrganizationContext;
use App\Http\Requests\WasteManagement\WasteBalanceRequest;
use App\Models\Organization;
use App\Services\B3DashboardService;
use Inertia\Inertia;
use Inertia\Response;

class B3DashboardController extends Controller
{
    public function index(WasteBalanceRequest $request, B3DashboardService $service): Response
    {
        $organization = $request->attributes->get(UseOrganizationContext::ATTRIBUTE);
        abort_unless($organization instanceof Organization && $request->user()->canAccessOrganization($organization->id), 403);

        return Inertia::render('waste-management/b3/Dashboard', [
            ...$service->dashboard($request->user(), $request->validated('start_date'), $request->validated('end_date')),
            'organizationName' => $organization->name,
        ]);
    }
}
