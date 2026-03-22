<?php

use App\Models\FabaMonthlyApproval;
use App\Models\FabaOpeningBalance;
use App\Models\FabaProductionEntry;
use App\Models\FabaUtilizationEntry;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TenantService;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolePermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

uses()->group('waste-management', 'faba');

beforeEach(function () {
    Storage::fake();

    $this->seed(RolesSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->seed(RolePermissionsSeeder::class);

    $this->organization = Organization::factory()->create([
        'code' => 'FABA',
        'schema_name' => 'tenant_faba',
    ]);

    $tenantService = app(TenantService::class);
    if (! $tenantService->schemaExists($this->organization->schema_name)) {
        $tenantService->createSchema($this->organization->schema_name);
    }

    $tenantService->switchToSchema($this->organization->schema_name);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--force' => true,
    ]);

    $tenantService->switchToPublic();

    $this->operator = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::where('slug', 'operator')->value('id'),
        'email_verified_at' => now(),
    ]);

    $this->supervisor = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::where('slug', 'supervisor')->value('id'),
        'email_verified_at' => now(),
    ]);

    $this->tenantService = $tenantService;

    $tenantService->switchToSchema($this->organization->schema_name);
    $this->vendor = Vendor::factory()->create([
        'is_active' => true,
    ]);
    $tenantService->switchToPublic();
});

afterEach(function () {
    $this->tenantService->switchToPublic();
});

test('operator can create faba production entry', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.faba.production.store'), [
            'transaction_date' => now()->toDateString(),
            'material_type' => 'fly_ash',
            'entry_type' => 'production',
            'quantity' => 10.5,
            'unit' => 'ton',
            'note' => 'Shift pagi',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->tenantService->switchToSchema($this->organization->schema_name);

    $this->assertDatabaseHas('faba_production_entries', [
        'material_type' => 'fly_ash',
        'entry_type' => 'production',
        'unit' => 'ton',
    ]);
});

test('operator can create external utilization with vendor and attachment', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.faba.utilization.store'), [
            'transaction_date' => now()->toDateString(),
            'material_type' => 'bottom_ash',
            'utilization_type' => 'external',
            'vendor_id' => $this->vendor->id,
            'quantity' => 8,
            'unit' => 'ton',
            'document_number' => 'DOC-001',
            'document_date' => now()->toDateString(),
            'attachment' => UploadedFile::fake()->create('berita-acara.pdf', 100),
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->tenantService->switchToSchema($this->organization->schema_name);

    expect(FabaUtilizationEntry::query()->first()?->attachment_path)->not->toBeNull();
});

test('external utilization requires vendor', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.faba.utilization.store'), [
            'transaction_date' => now()->toDateString(),
            'material_type' => 'fly_ash',
            'utilization_type' => 'external',
            'quantity' => 3,
            'unit' => 'ton',
        ]);

    $response->assertSessionHasErrors(['vendor_id']);
});

test('external utilization requires document metadata', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.faba.utilization.store'), [
            'transaction_date' => now()->toDateString(),
            'material_type' => 'fly_ash',
            'utilization_type' => 'external',
            'vendor_id' => $this->vendor->id,
            'quantity' => 3,
            'unit' => 'ton',
        ]);

    $response->assertSessionHasErrors(['document_number', 'document_date']);
});

test('monthly recap page renders calculated totals', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);

    FabaOpeningBalance::factory()->create([
        'year' => 2026,
        'month' => 3,
        'material_type' => FabaProductionEntry::MATERIAL_FLY_ASH,
        'quantity' => 2,
    ]);

    FabaProductionEntry::factory()->create([
        'transaction_date' => '2026-03-05',
        'material_type' => 'fly_ash',
        'quantity' => 20,
    ]);

    FabaUtilizationEntry::factory()->create([
        'transaction_date' => '2026-03-10',
        'material_type' => 'fly_ash',
        'utilization_type' => 'internal',
        'vendor_id' => null,
        'quantity' => 5,
    ]);

    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.recaps.monthly', ['year' => 2026, 'month' => 3]));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/faba/recaps/Monthly')
        ->where('detail.recap.total_production', 20)
        ->where('detail.recap.total_utilization', 5)
        ->where('detail.recap.opening_balance', 2)
        ->where('detail.recap.closing_balance', 17)
    );
});

test('monthly recap defaults to latest period with data', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);

    FabaProductionEntry::factory()->create([
        'transaction_date' => '2025-12-05',
        'quantity' => 10,
    ]);

    FabaProductionEntry::factory()->create([
        'transaction_date' => '2026-02-05',
        'quantity' => 14,
    ]);

    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.recaps.monthly'));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/faba/recaps/Monthly')
        ->where('filters.year', 2026)
        ->where('filters.month', 2)
        ->where('detail.recap.period_label', 'Februari 2026')
        ->where('detail.recap.total_production', 14)
        ->where('resolvedFromLatestPeriod', true)
        ->has('availablePeriods', 2)
    );
});

test('monthly recap allows explicit empty period without redirecting to latest period', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);

    FabaProductionEntry::factory()->create([
        'transaction_date' => '2026-02-05',
        'quantity' => 14,
    ]);

    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.recaps.monthly', ['year' => 2026, 'month' => 3]));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/faba/recaps/Monthly')
        ->where('filters.year', 2026)
        ->where('filters.month', 3)
        ->where('detail.recap.period_label', 'Maret 2026')
        ->where('detail.recap.total_production', 0)
        ->where('detail.recap.total_utilization', 0)
        ->where('resolvedFromLatestPeriod', false)
    );
});

test('approval index only shows periods that have transactions with month labels', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaProductionEntry::factory()->create([
        'transaction_date' => '2026-03-05',
        'quantity' => 12,
    ]);
    FabaUtilizationEntry::factory()->create([
        'transaction_date' => '2026-05-10',
        'utilization_type' => 'internal',
        'vendor_id' => null,
        'quantity' => 2,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.approvals.index', ['year' => 2026]));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/faba/approvals/Index')
        ->has('periods', 2)
        ->where('periods.0.period_label', 'Maret 2026')
        ->where('periods.1.period_label', 'Mei 2026')
    );
});

test('operator can submit month and supervisor can approve it', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaProductionEntry::factory()->create([
        'transaction_date' => '2026-03-05',
        'quantity' => 12,
    ]);
    $this->tenantService->switchToPublic();

    $submitResponse = $this->actingAs($this->operator)
        ->post(route('waste-management.faba.approvals.submit'), [
            'year' => 2026,
            'month' => 3,
        ]);

    $submitResponse->assertRedirect();

    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(FabaMonthlyApproval::query()->first()?->status)->toBe('submitted');
    $this->tenantService->switchToPublic();

    $approveResponse = $this->actingAs($this->supervisor)
        ->post(route('waste-management.faba.approvals.approve', ['year' => 2026, 'month' => 3]), [
            'approval_note' => 'OK',
        ]);

    $approveResponse->assertRedirect();

    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(FabaMonthlyApproval::query()->first()?->status)->toBe('approved');
});

test('approved period locks production edits', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $entry = FabaProductionEntry::factory()->create([
        'transaction_date' => '2026-03-05',
        'quantity' => 12,
    ]);
    FabaMonthlyApproval::factory()->create([
        'year' => 2026,
        'month' => 3,
        'status' => FabaMonthlyApproval::STATUS_APPROVED,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->put(route('waste-management.faba.production.update', $entry), [
            'transaction_date' => '2026-03-05',
            'material_type' => 'fly_ash',
            'entry_type' => 'production',
            'quantity' => 15,
            'unit' => 'ton',
        ]);

    $response->assertForbidden();
});

test('approved period can be reopened with reason', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaMonthlyApproval::factory()->create([
        'year' => 2026,
        'month' => 3,
        'status' => FabaMonthlyApproval::STATUS_APPROVED,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->supervisor)
        ->post(route('waste-management.faba.approvals.reopen', ['year' => 2026, 'month' => 3]), [
            'reopen_note' => 'Perlu revisi data dokumen',
        ]);

    $response->assertRedirect();

    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(FabaMonthlyApproval::query()->first()?->status)->toBe(FabaMonthlyApproval::STATUS_REJECTED);
    expect(FabaMonthlyApproval::query()->first()?->rejection_note)->toBe('Perlu revisi data dokumen');
    $this->tenantService->switchToPublic();
});

test('supervisor can store opening balance', function () {
    $response = $this->actingAs($this->supervisor)
        ->post(route('waste-management.faba.recaps.openingBalance.store'), [
            'year' => 2026,
            'month' => 1,
            'material_type' => 'fly_ash',
            'quantity' => 25,
            'note' => 'Saldo awal implementasi',
        ]);

    $response->assertRedirect();

    $this->tenantService->switchToSchema($this->organization->schema_name);
    $this->assertDatabaseHas('faba_opening_balances', [
        'year' => 2026,
        'month' => 1,
        'material_type' => 'fly_ash',
    ]);
    $this->tenantService->switchToPublic();
});

test('operator cannot store opening balance', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.faba.recaps.openingBalance.store'), [
            'year' => 2026,
            'month' => 1,
            'material_type' => 'fly_ash',
            'quantity' => 25,
        ]);

    $response->assertForbidden();
});

test('cannot submit empty period for approval', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.faba.approvals.submit'), [
            'year' => 2026,
            'month' => 4,
        ]);

    $response->assertSessionHas('error');
});

test('reject requires rejection note', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaMonthlyApproval::factory()->create([
        'year' => 2026,
        'month' => 3,
        'status' => FabaMonthlyApproval::STATUS_SUBMITTED,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->supervisor)
        ->post(route('waste-management.faba.approvals.reject', ['year' => 2026, 'month' => 3]), []);

    $response->assertSessionHasErrors(['rejection_note']);
});

test('cannot move utilization into locked period', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $entry = FabaUtilizationEntry::factory()->create([
        'transaction_date' => '2026-02-10',
        'utilization_type' => 'internal',
        'vendor_id' => null,
    ]);
    FabaMonthlyApproval::factory()->create([
        'year' => 2026,
        'month' => 3,
        'status' => FabaMonthlyApproval::STATUS_APPROVED,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->put(route('waste-management.faba.utilization.update', $entry), [
            'transaction_date' => '2026-03-10',
            'material_type' => 'fly_ash',
            'utilization_type' => 'internal',
            'vendor_id' => null,
            'quantity' => 5,
            'unit' => 'ton',
        ]);

    $response->assertSessionHas('error');
});

test('faba export endpoints require correct permission', function () {
    $guest = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => null,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($guest)
        ->get(route('waste-management.faba.reports.index'));

    $response->assertForbidden();
});

test('monthly report csv can be downloaded', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaOpeningBalance::factory()->create([
        'year' => 2026,
        'month' => 3,
        'material_type' => 'fly_ash',
        'quantity' => 10,
    ]);
    FabaProductionEntry::factory()->create([
        'transaction_date' => '2026-03-05',
        'material_type' => 'fly_ash',
        'quantity' => 20,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.reports.monthly.csv', ['year' => 2026, 'month' => 3]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('period_label,"Maret 2026"')
        ->toContain('opening_balance,10')
        ->toContain('closing_balance,30');
});

test('production export csv respects report filters', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaProductionEntry::factory()->create([
        'transaction_date' => '2026-03-05',
        'material_type' => 'fly_ash',
        'entry_type' => 'production',
        'quantity' => 7,
    ]);
    FabaProductionEntry::factory()->create([
        'transaction_date' => '2026-04-05',
        'material_type' => 'bottom_ash',
        'entry_type' => 'workshop',
        'quantity' => 11,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.production.export.csv', [
            'year' => 2026,
            'month' => 3,
            'material_type' => 'fly_ash',
            'entry_type' => 'production',
        ]));

    $response->assertSuccessful();
    expect($response->streamedContent())
        ->toContain('FP-202603-')
        ->not->toContain('FP-202604-');
});

test('faba demo seed command creates deterministic tenant data', function () {
    CarbonImmutable::setTestNow('2026-03-19 10:00:00');

    $response = Artisan::call('faba:seed-demo', [
        '--tenant' => 'FABADEMO',
        '--schema' => 'tenant_faba_demo',
        '--fresh-tenant' => true,
    ]);

    expect($response)->toBe(0);

    $organization = Organization::query()->where('code', 'FABADEMO')->first();

    expect($organization)->not->toBeNull();
    expect($this->tenantService->schemaExists('tenant_faba_demo'))->toBeTrue();

    $this->tenantService->switchToSchema('tenant_faba_demo');

    expect(Vendor::query()->count())->toBe(3);
    expect(FabaProductionEntry::query()->count())->toBe(35);
    expect(FabaUtilizationEntry::query()->count())->toBe(25);
    expect(FabaOpeningBalance::query()->count())->toBe(4);
    expect(FabaMonthlyApproval::query()->count())->toBe(3);
    expect(
        FabaMonthlyApproval::query()->orderBy('year')->orderBy('month')->pluck('status')->all()
    )->toBe([
        FabaMonthlyApproval::STATUS_APPROVED,
        FabaMonthlyApproval::STATUS_APPROVED,
        FabaMonthlyApproval::STATUS_SUBMITTED,
    ]);

    expect(
        FabaUtilizationEntry::query()
            ->where('utilization_type', FabaUtilizationEntry::TYPE_EXTERNAL)
            ->where(function ($query) {
                $query->whereNull('vendor_id')
                    ->orWhereNull('document_number')
                    ->orWhereNull('document_date');
            })->exists()
    )->toBeFalse();

    $recapService = app(\App\Services\FabaRecapService::class);
    $decemberRecap = $recapService->getMonthlyRecap(2025, 12);
    $januaryRecap = $recapService->getMonthlyRecap(2026, 1);
    $februaryRecap = $recapService->getMonthlyRecap(2026, 2);

    expect($januaryRecap['opening_balance'])->toBe($decemberRecap['closing_balance']);
    expect($februaryRecap['opening_balance'])->toBe($januaryRecap['closing_balance']);
    expect(collect($februaryRecap['warnings'])->pluck('code')->contains('missing_opening_balance'))->toBeTrue();

    $this->tenantService->switchToPublic();

    expect(
        User::query()->whereIn('email', [
            'faba.supervisor.demo@local.test',
            'faba.operator.demo@local.test',
        ])->count()
    )->toBe(2);
    CarbonImmutable::setTestNow();
});

test('faba reports default to latest period with data', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);

    FabaProductionEntry::factory()->create([
        'transaction_date' => '2026-01-05',
        'quantity' => 9,
    ]);

    FabaProductionEntry::factory()->create([
        'transaction_date' => '2026-02-05',
        'quantity' => 18,
    ]);

    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.reports.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/faba/reports/Index')
        ->where('filters.year', 2026)
        ->where('filters.month', 2)
        ->where('monthlyRecap.period_label', 'Februari 2026')
        ->where('resolvedFromLatestPeriod', true)
        ->has('availablePeriods', 2)
    );
});

test('faba demo seed can populate an existing tenant without overwriting organization metadata', function () {
    CarbonImmutable::setTestNow('2026-03-19 10:00:00');

    $existingOrganization = Organization::factory()->create([
        'code' => 'TWMS',
        'name' => 'Tenant Produksi TWMS',
        'schema_name' => 'tenant_twms_existing_faba',
    ]);

    if (! $this->tenantService->schemaExists($existingOrganization->schema_name)) {
        $this->tenantService->createSchema($existingOrganization->schema_name);
    }

    $this->tenantService->switchToSchema($existingOrganization->schema_name);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--force' => true,
    ]);
    $this->tenantService->switchToPublic();

    $john = User::factory()->create([
        'email' => 'john@d.co',
        'organization_id' => $existingOrganization->id,
        'is_super_admin' => true,
        'role_id' => Role::where('slug', 'super_admin')->value('id'),
    ]);

    $response = Artisan::call('faba:seed-demo', [
        '--tenant' => 'TWMS',
        '--schema' => $existingOrganization->schema_name,
    ]);

    expect($response)->toBe(0);

    $existingOrganization->refresh();
    expect($existingOrganization->name)->toBe('Tenant Produksi TWMS');
    expect(User::query()->find($john->id))->not->toBeNull();

    $this->tenantService->switchToSchema($existingOrganization->schema_name);
    expect(FabaProductionEntry::query()->count())->toBe(35);
    expect(FabaUtilizationEntry::query()->count())->toBe(25);
    expect(FabaMonthlyApproval::query()->count())->toBe(3);
    $this->tenantService->switchToPublic();

    CarbonImmutable::setTestNow();
});

test('faba demo seed rejects fresh mode for an existing non-demo tenant', function () {
    $existingOrganization = Organization::factory()->create([
        'code' => 'TWMS',
        'name' => 'Tenant Produksi TWMS',
        'schema_name' => 'tenant_twms_no_fresh',
    ]);

    $response = Artisan::call('faba:seed-demo', [
        '--tenant' => 'TWMS',
        '--schema' => $existingOrganization->schema_name,
        '--fresh-tenant' => true,
    ]);

    expect($response)->toBe(1);
});
