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
    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.reports.monthly.csv', ['year' => 2026, 'month' => 3]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});
