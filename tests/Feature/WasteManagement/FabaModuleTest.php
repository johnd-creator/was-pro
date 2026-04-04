<?php

use App\Models\FabaInternalDestination;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaMonthlyClosingSnapshot;
use App\Models\FabaMovement;
use App\Models\FabaOpeningBalance;
use App\Models\FabaPurpose;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TenantService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses()->group('waste-management', 'faba');

function spreadsheetRows(TestResponse $response): array
{
    $file = $response->baseResponse->getFile();
    expect($file)->not->toBeNull();

    $spreadsheet = IOFactory::load($file->getPathname());

    return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
}

beforeEach(function () {
    Storage::fake();

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
    $this->internalDestination = FabaInternalDestination::factory()->create([
        'name' => 'Workshop Internal',
        'slug' => 'workshop-internal',
    ]);
    $this->purpose = FabaPurpose::factory()->create([
        'name' => 'Pemanfaatan Mitra',
        'slug' => 'pemanfaatan-mitra',
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
            'movement_type' => 'production',
            'quantity' => 10.5,
            'unit' => 'ton',
            'note' => 'Shift pagi',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->tenantService->switchToSchema($this->organization->schema_name);

    $this->assertDatabaseHas('faba_movements', [
        'material_type' => 'fly_ash',
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'unit' => 'ton',
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
    ]);
    expect(FabaMovement::query()->where('movement_type', FabaMovement::TYPE_PRODUCTION)->count())->toBe(1);
    expect(FabaMovement::query()->whereNotNull('reference_id')->count())->toBe(0);
});

test('operator can create external utilization with vendor and attachment', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.faba.utilization.store'), [
            'transaction_date' => now()->toDateString(),
            'material_type' => 'bottom_ash',
            'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL,
            'vendor_id' => $this->vendor->id,
            'purpose_id' => $this->purpose->id,
            'quantity' => 8,
            'unit' => 'ton',
            'document_number' => 'DOC-001',
            'document_date' => now()->toDateString(),
            'attachment' => UploadedFile::fake()->create('berita-acara.pdf', 100),
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->tenantService->switchToSchema($this->organization->schema_name);

    $movement = FabaMovement::query()
        ->where('movement_type', FabaMovement::TYPE_UTILIZATION_EXTERNAL)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement?->attachment_path)->not->toBeNull();
    expect($movement?->vendor_id)->toBe($this->vendor->id);
    expect($movement?->purpose_id)->toBe($this->purpose->id);
});

test('external utilization requires vendor', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.faba.utilization.store'), [
            'transaction_date' => now()->toDateString(),
            'material_type' => 'fly_ash',
            'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL,
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
            'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL,
            'vendor_id' => $this->vendor->id,
            'quantity' => 3,
            'unit' => 'ton',
        ]);

    $response->assertSessionHasErrors(['document_number', 'document_date']);
});

test('internal utilization requires internal destination', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.faba.utilization.store'), [
            'transaction_date' => now()->toDateString(),
            'material_type' => 'fly_ash',
            'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL,
            'quantity' => 3,
            'unit' => 'ton',
        ]);

    $response->assertSessionHasErrors(['internal_destination_id']);
});

test('utilization index renders active vendor, destination, and purpose options', function () {
    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.utilization.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/faba/utilization/Index')
        ->has('vendors', 1)
        ->has('internalDestinations', 1)
        ->has('purposes', 1)
    );
});

test('tenant schema drops legacy faba entry tables after cleanup migration', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);

    expect(Schema::hasTable('faba_movements'))->toBeTrue();
    expect(Schema::hasTable('faba_production_entries'))->toBeFalse();
    expect(Schema::hasTable('faba_utilization_entries'))->toBeFalse();
});

test('monthly recap page renders calculated totals', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);

    FabaOpeningBalance::factory()->create([
        'year' => 2026,
        'month' => 3,
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'quantity' => 2,
    ]);

    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-05',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 3,
        'quantity' => 20,
    ]);

    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-10',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL,
        'stock_effect' => FabaMovement::STOCK_EFFECT_OUT,
        'internal_destination_id' => $this->internalDestination->id,
        'period_year' => 2026,
        'period_month' => 3,
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

    FabaMovement::factory()->create([
        'transaction_date' => '2025-12-05',
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2025,
        'period_month' => 12,
        'quantity' => 10,
    ]);

    FabaMovement::factory()->create([
        'transaction_date' => '2026-02-05',
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 2,
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

    FabaMovement::factory()->create([
        'transaction_date' => '2026-02-05',
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 2,
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
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-05',
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 3,
        'quantity' => 12,
    ]);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-05-10',
        'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL,
        'stock_effect' => FabaMovement::STOCK_EFFECT_OUT,
        'internal_destination_id' => $this->internalDestination->id,
        'period_year' => 2026,
        'period_month' => 5,
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
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-05',
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 3,
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
    expect(FabaMonthlyClosingSnapshot::query()->forPeriod(2026, 3)->exists())->toBeTrue();
});

test('approved period locks production edits', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $entry = FabaMovement::factory()->create([
        'transaction_date' => '2026-03-05',
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 3,
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
            'movement_type' => 'production',
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
    expect(FabaMovement::query()->where('movement_type', FabaMovement::TYPE_OPENING_BALANCE)->count())->toBe(1);
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
    $entry = FabaMovement::factory()->create([
        'transaction_date' => '2026-02-10',
        'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL,
        'stock_effect' => FabaMovement::STOCK_EFFECT_OUT,
        'internal_destination_id' => $this->internalDestination->id,
        'period_year' => 2026,
        'period_month' => 2,
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
            'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL,
            'vendor_id' => null,
            'internal_destination_id' => $this->internalDestination->id,
            'quantity' => 5,
            'unit' => 'ton',
        ]);

    $response->assertSessionHas('error');
});

test('operator can create faba adjustment in movement', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.faba.adjustments.store'), [
            'transaction_date' => '2026-03-12',
            'material_type' => FabaMovement::MATERIAL_FLY_ASH,
            'movement_type' => FabaMovement::TYPE_ADJUSTMENT_IN,
            'quantity' => 4.5,
            'unit' => 'ton',
            'note' => 'Koreksi opname',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $this->tenantService->switchToSchema($this->organization->schema_name);

    $this->assertDatabaseHas('faba_movements', [
        'movement_type' => FabaMovement::TYPE_ADJUSTMENT_IN,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
    ]);
});

test('adjustment out cannot exceed available stock', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-01',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 3,
        'quantity' => 5,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.faba.adjustments.store'), [
            'transaction_date' => '2026-03-12',
            'material_type' => FabaMovement::MATERIAL_FLY_ASH,
            'movement_type' => FabaMovement::TYPE_ADJUSTMENT_OUT,
            'quantity' => 9,
            'unit' => 'ton',
            'note' => 'Koreksi minus',
        ]);

    $response->assertSessionHasErrors(['quantity']);
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

test('stock card page renders movement ledger rows', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-05',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 3,
        'quantity' => 10,
    ]);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-08',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL,
        'stock_effect' => FabaMovement::STOCK_EFFECT_OUT,
        'internal_destination_id' => $this->internalDestination->id,
        'period_year' => 2026,
        'period_month' => 3,
        'quantity' => 3,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.recaps.stockCard', ['year' => 2026, 'month' => 3]));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/faba/recaps/StockCard')
        ->where('stockCard.summary.count', 2)
        ->has('stockCard.rows', 2)
    );
});

test('official faba reports no longer expose csv routes', function () {
    expect(Route::has('waste-management.faba.reports.monthly.csv'))->toBeFalse()
        ->and(Route::has('waste-management.faba.reports.yearly.csv'))->toBeFalse()
        ->and(Route::has('waste-management.faba.reports.vendors.csv'))->toBeFalse()
        ->and(Route::has('waste-management.faba.reports.internal-destinations.csv'))->toBeFalse()
        ->and(Route::has('waste-management.faba.reports.purposes.csv'))->toBeFalse()
        ->and(Route::has('waste-management.faba.reports.stock-card.csv'))->toBeFalse()
        ->and(Route::has('waste-management.faba.reports.anomalies.csv'))->toBeFalse();
});

test('monthly report xlsx can be downloaded', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaOpeningBalance::factory()->create([
        'year' => 2026,
        'month' => 3,
        'material_type' => 'fly_ash',
        'quantity' => 10,
    ]);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-05',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 3,
        'quantity' => 20,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.reports.monthly.xlsx', ['year' => 2026, 'month' => 3]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');

    $rows = spreadsheetRows($response);
    $flattened = collect($rows)->flatten()->implode('|');

    expect($flattened)->toContain('Laporan Rekap Closing Bulanan FABA')
        ->toContain('30')
        ->toContain('20');
});

test('monthly report pdf can be downloaded', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaOpeningBalance::factory()->create([
        'year' => 2026,
        'month' => 3,
        'material_type' => 'fly_ash',
        'quantity' => 10,
    ]);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-05',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 3,
        'quantity' => 20,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.reports.monthly.pdf', ['year' => 2026, 'month' => 3]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

test('yearly report xlsx can be downloaded', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-05',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 3,
        'quantity' => 20,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.reports.yearly.xlsx', ['year' => 2026]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');

    $rows = spreadsheetRows($response);
    $flattened = collect($rows)->flatten()->implode('|');

    expect($flattened)->toContain('Laporan Rekap Tahunan FABA')
        ->toContain('Maret 2026');
});

test('vendors report pdf can be downloaded', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-05',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL,
        'stock_effect' => FabaMovement::STOCK_EFFECT_OUT,
        'vendor_id' => $this->vendor->id,
        'document_number' => 'DOC-VENDOR-001',
        'document_date' => '2026-03-05',
        'period_year' => 2026,
        'period_month' => 3,
        'quantity' => 8,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.reports.vendors.pdf', ['year' => 2026]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

test('stock card report xlsx can be downloaded', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-05',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 3,
        'quantity' => 20,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.reports.stock-card.xlsx', ['year' => 2026, 'month' => 3]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');

    $rows = spreadsheetRows($response);
    $flattened = collect($rows)->flatten()->implode('|');

    expect($flattened)->toContain('Laporan Stock Card FABA')
        ->toContain('FPR-');
});

test('anomaly report pdf can be downloaded', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-05',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL,
        'stock_effect' => FabaMovement::STOCK_EFFECT_OUT,
        'vendor_id' => $this->vendor->id,
        'document_number' => 'DOC-ERR-001',
        'document_date' => '2026-03-05',
        'period_year' => 2026,
        'period_month' => 3,
        'quantity' => 15,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.reports.anomalies.pdf', ['year' => 2026, 'month' => 3]));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
    expect($response->getContent())->toStartWith('%PDF');
});

test('production export csv respects report filters', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-05',
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 3,
        'quantity' => 7,
    ]);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-04-05',
        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
        'movement_type' => FabaMovement::TYPE_WORKSHOP,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 4,
        'quantity' => 11,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.faba.production.export.csv', [
            'year' => 2026,
            'month' => 3,
            'material_type' => 'fly_ash',
            'movement_type' => 'production',
        ]));

    $response->assertSuccessful();
    expect($response->streamedContent())
        ->toContain('FM-PROD-20260305-')
        ->not->toContain('FM-PROD-20260405-');
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
    expect(FabaInternalDestination::query()->count())->toBe(2);
    expect(FabaPurpose::query()->count())->toBe(3);
    expect(FabaOpeningBalance::query()->count())->toBe(24);
    expect(FabaMovement::query()->count())->toBe(132);
    expect(FabaMonthlyApproval::query()->count())->toBe(12);
    expect(FabaMonthlyClosingSnapshot::query()->count())->toBe(10);
    expect(
        FabaMonthlyApproval::query()->orderBy('year')->orderBy('month')->pluck('status')->all()
    )->toBe([
        FabaMonthlyApproval::STATUS_APPROVED,
        FabaMonthlyApproval::STATUS_APPROVED,
        FabaMonthlyApproval::STATUS_APPROVED,
        FabaMonthlyApproval::STATUS_APPROVED,
        FabaMonthlyApproval::STATUS_REJECTED,
        FabaMonthlyApproval::STATUS_APPROVED,
        FabaMonthlyApproval::STATUS_APPROVED,
        FabaMonthlyApproval::STATUS_APPROVED,
        FabaMonthlyApproval::STATUS_APPROVED,
        FabaMonthlyApproval::STATUS_APPROVED,
        FabaMonthlyApproval::STATUS_APPROVED,
        FabaMonthlyApproval::STATUS_SUBMITTED,
    ]);

    expect(FabaMovement::query()->distinct()->pluck('movement_type')->sort()->values()->all())
        ->toBe([
            FabaMovement::TYPE_ADJUSTMENT_IN,
            FabaMovement::TYPE_ADJUSTMENT_OUT,
            FabaMovement::TYPE_DISPOSAL_POK,
            FabaMovement::TYPE_OPENING_BALANCE,
            FabaMovement::TYPE_PRODUCTION,
            FabaMovement::TYPE_REJECT,
            FabaMovement::TYPE_UTILIZATION_EXTERNAL,
            FabaMovement::TYPE_UTILIZATION_INTERNAL,
            FabaMovement::TYPE_WORKSHOP,
        ]);

    expect(
        FabaMovement::query()
            ->where('movement_type', FabaMovement::TYPE_UTILIZATION_EXTERNAL)
            ->where(function ($query) {
                $query->whereNull('vendor_id')
                    ->orWhereNull('document_number')
                    ->orWhereNull('document_date');
            })->exists()
    )->toBeFalse();

    $recapService = app(\App\Services\FabaRecapService::class);
    $firstRecap = $recapService->getMonthlyRecap(2025, 3);
    $rejectedRecap = $recapService->getMonthlyRecap(2025, 7);
    $latestClosedRecap = $recapService->getMonthlyRecap(2026, 2);

    expect($firstRecap['opening_balance'])->toBe(200.0);
    expect($rejectedRecap['opening_balance'])->toBe(256.0);
    expect($latestClosedRecap['opening_balance'])->toBe(354.0);
    expect(collect($latestClosedRecap['warnings'])->pluck('code')->contains('missing_opening_balance'))->toBeFalse();

    $this->tenantService->switchToPublic();

    expect(
        User::query()->whereIn('email', [
            'john@d.co',
            'faba.supervisor.demo@local.test',
            'faba.operator.demo@local.test',
        ])->count()
    )->toBe(3);
    CarbonImmutable::setTestNow();
});

test('faba reports default to latest period with data', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);

    FabaMovement::factory()->create([
        'transaction_date' => '2026-01-05',
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 1,
        'quantity' => 9,
    ]);

    FabaMovement::factory()->create([
        'transaction_date' => '2026-02-05',
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'period_year' => 2026,
        'period_month' => 2,
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
    expect(FabaMovement::query()->count())->toBe(132);
    expect(FabaMonthlyApproval::query()->count())->toBe(12);
    expect(FabaMonthlyClosingSnapshot::query()->count())->toBe(10);
    $this->tenantService->switchToPublic();

    CarbonImmutable::setTestNow();
});

test('faba demo seed can run while current search path is a tenant schema', function () {
    CarbonImmutable::setTestNow('2026-03-19 10:00:00');

    $existingOrganization = Organization::factory()->create([
        'code' => 'TWMS',
        'name' => 'Tenant Produksi TWMS',
        'schema_name' => 'tenant_twms_search_path_faba',
    ]);

    if (! $this->tenantService->schemaExists($existingOrganization->schema_name)) {
        $this->tenantService->createSchema($existingOrganization->schema_name);
    }

    $this->tenantService->switchToSchema($existingOrganization->schema_name);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--force' => true,
    ]);

    $response = Artisan::call('faba:seed-demo', [
        '--tenant' => 'TWMS',
        '--schema' => $existingOrganization->schema_name,
    ]);

    expect($response)->toBe(0);

    $this->tenantService->switchToSchema($existingOrganization->schema_name);
    expect(FabaMovement::query()->count())->toBe(132);
    expect(FabaMonthlyApproval::query()->count())->toBe(12);
    expect(FabaMonthlyClosingSnapshot::query()->count())->toBe(10);
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

test('faba demo seed rejects fresh mode for protected default demo schema on a non-testing database', function () {
    config()->set('database.connections.'.config('database.default').'.database', 'was_pro');

    $response = Artisan::call('faba:seed-demo', [
        '--fresh-tenant' => true,
    ]);

    expect($response)->toBe(1);
    expect(Artisan::output())->toContain('hanya diizinkan pada database testing');
});

test('integrated demo seed command prepares waste and faba data in one tenant', function () {
    CarbonImmutable::setTestNow('2026-03-19 10:00:00');

    $response = Artisan::call('demo:seed', [
        '--tenant' => 'TWMSALLDEMO',
        '--schema' => 'tenant_twms_all_demo',
        '--fresh-tenant' => true,
    ]);

    expect($response)->toBe(0);

    $organization = Organization::query()->where('code', 'TWMSALLDEMO')->first();

    expect($organization)->not->toBeNull()
        ->and($organization?->schema_name)->toBe('tenant_twms_all_demo');
    expect($this->tenantService->schemaExists('tenant_twms_all_demo'))->toBeTrue();

    $this->tenantService->switchToSchema('tenant_twms_all_demo');

    expect(\App\Models\WasteCategory::query()->count())->toBe(3)
        ->and(\App\Models\WasteType::query()->count())->toBe(4)
        ->and(\App\Models\WasteRecord::query()->count())->toBe(144)
        ->and(\App\Models\WasteTransportation::query()->count())->toBe(60)
        ->and(Vendor::query()->count())->toBe(6)
        ->and(FabaInternalDestination::query()->count())->toBe(2)
        ->and(FabaPurpose::query()->count())->toBe(3)
        ->and(FabaOpeningBalance::query()->count())->toBe(24)
        ->and(FabaMovement::query()->count())->toBe(132)
        ->and(FabaMonthlyApproval::query()->count())->toBe(12)
        ->and(FabaMonthlyClosingSnapshot::query()->count())->toBe(10);

    $this->tenantService->switchToPublic();

    expect(
        User::query()->whereIn('email', [
            'john@d.co',
            'wm.supervisor.demo@local.test',
            'wm.operator.demo@local.test',
            'faba.supervisor.demo@local.test',
            'faba.operator.demo@local.test',
        ])->where('organization_id', $organization?->id)->count()
    )->toBe(5);

    CarbonImmutable::setTestNow();
});
