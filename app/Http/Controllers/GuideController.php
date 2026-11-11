<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $ready = $request->attributes->get('guide_tenant_ready', false);

        return Inertia::render('Guide', [
            'guideRole' => $user->isSuperAdmin() ? ['slug' => 'super_admin', 'name' => 'Super Admin'] : [
                'slug' => $user->role?->slug,
                'name' => $user->role?->name ?? 'Belum memiliki role',
            ],
            'guideTenantReady' => $ready,
            'guideModules' => [
                'waste' => $ready && Schema::hasTable('waste_records') && Schema::hasTable('waste_haulings'),
                'faba' => $ready && Schema::hasTable('faba_movements') && Schema::hasTable('faba_monthly_approvals'),
                'fabaMasters' => $ready && Schema::hasTable('faba_internal_destinations') && Schema::hasTable('faba_purposes'),
                'audit' => $ready && Schema::hasTable('operational_audit_logs'),
            ],
        ]);
    }
}
