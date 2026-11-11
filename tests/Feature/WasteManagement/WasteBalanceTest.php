<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use App\Models\WasteType;
use App\Services\TenantService;
use App\Services\WasteBalanceService;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->organization = Organization::factory()->create();
    $this->tenantService = app(TenantService::class);
    $this->tenantService->createSchema($this->organization->schema_name);
    $this->tenantService->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToPublic();
    $this->admin = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::query()->where('slug', 'super_admin')->value('id'),
        'is_super_admin' => true,
        'email_verified_at' => now(),
    ]);
    $this->actingAs($this->admin);
    $this->tenantService->switchToSchema($this->organization->schema_name);
});

afterEach(function () {
    $this->tenantService->switchToPublic();
});

function balanceUrl(string $name = 'index', array $query = []): string
{
    return route('waste-management.balance.'.$name, [
        'organization_id' => test()->organization->id,
        'start_date' => '2026-10-01', 'end_date' => '2026-10-31', ...$query,
    ]);
}

function balanceRecord(array $attributes = []): WasteRecord
{
    return WasteRecord::factory()->approved()->create([
        'created_by' => test()->admin->id, 'date' => '2026-09-01', 'quantity' => 100, 'unit' => 'kg', ...$attributes,
    ]);
}

function balanceHauling(WasteRecord $record, array $attributes = []): WasteHauling
{
    return WasteHauling::factory()->approved()->create([
        'waste_record_id' => $record->id, 'created_by' => test()->admin->id,
        'hauling_date' => '2026-10-15', 'quantity' => 10, 'unit' => $record->unit, ...$attributes,
    ]);
}

test('balance carries opening and includes both period boundaries by transaction date', function () {
    $record = balanceRecord();
    balanceHauling($record, ['hauling_date' => '2026-09-30', 'quantity' => 20]);
    balanceHauling($record, ['hauling_date' => '2026-10-01', 'quantity' => 10]);
    balanceHauling($record, ['hauling_date' => '2026-10-31', 'quantity' => 15]);
    balanceHauling($record, ['hauling_date' => '2026-11-01', 'quantity' => 50]);
    foreach (['2026-10-01', '2026-10-31', '2026-11-01'] as $date) {
        balanceRecord(['waste_type_id' => $record->waste_type_id, 'date' => $date, 'quantity' => 5.25]);
    }
    $this->get(balanceUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/records/Balance')
        ->where('report.basis', WasteBalanceService::BASIS)
        ->has('report.rows', 1)
        ->where('report.rows.0.opening', 80)->where('report.rows.0.incoming', 10.5)
        ->where('report.rows.0.outgoing', 25)->where('report.rows.0.closing', 65.5)
        ->has('report.rows.0.references', 3)
        ->where('report.rows.0.anomaly_count', 0));
});

test('balance keeps exact units and waste types separate including per unit totals', function () {
    $type = WasteType::factory()->create(['name' => 'A']);
    foreach ([['kg', 100], ['liter', 20], ['ton', 2]] as [$unit, $quantity]) {
        balanceRecord(['waste_type_id' => $type->id, 'unit' => $unit, 'quantity' => $quantity]);
    }
    balanceRecord(['waste_type_id' => WasteType::factory()->create(['name' => 'B'])->id, 'quantity' => 25]);
    $this->get(balanceUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('report.rows', 4)->where('report.totals', [
            ['unit' => 'kg', 'opening' => 125, 'incoming' => 0, 'outgoing' => 0, 'closing' => 125],
            ['unit' => 'liter', 'opening' => 20, 'incoming' => 0, 'outgoing' => 0, 'closing' => 20],
            ['unit' => 'ton', 'opening' => 2, 'incoming' => 0, 'outgoing' => 0, 'closing' => 2],
        ])->missing('report.total_quantity'));
});

test('balance uses current approved nondeleted records and approved nondeleted haulings only', function () {
    $record = balanceRecord();
    foreach (['draft', 'pending_review', 'rejected'] as $status) {
        $excluded = balanceRecord(['status' => $status]);
        balanceHauling($excluded);
    }
    balanceRecord(['deleted_at' => now()]);
    foreach (['draft', 'pending_approval', 'rejected', 'cancelled'] as $status) {
        balanceHauling($record, ['status' => $status]);
    }
    balanceHauling($record, ['deleted_at' => now()]);
    balanceHauling($record, ['quantity' => 12.34, 'approved_at' => '2026-11-11 10:00:00']);
    $this->get(balanceUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('report.rows', 1)->where('report.rows.0.opening', 100)
        ->where('report.rows.0.outgoing', 12.34)->where('report.rows.0.closing', 87.66));
});

test('balance exposes negative and mismatched unit anomalies without clamping or hiding future records', function () {
    $record = balanceRecord(['date' => '2026-11-01']);
    balanceHauling($record, ['hauling_date' => '2026-09-30', 'quantity' => 10]);
    balanceHauling($record, ['quantity' => 20]);
    balanceHauling($record, ['quantity' => 2, 'unit' => 'liter']);
    $this->get(balanceUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('report.rows', 2)
        ->where('report.rows.0.unit', 'kg')->where('report.rows.0.opening', -10)
        ->where('report.rows.0.incoming', 0)->where('report.rows.0.outgoing', 20)
        ->where('report.rows.0.closing', -30)->where('report.rows.0.anomaly_count', 3)
        ->where('report.rows.1.unit', 'liter')->where('report.rows.1.closing', -2)
        ->where('report.rows.1.anomaly_count', 3));
});

test('balance scopes records to their owner for own permission and exports the same scope', function () {
    $this->tenantService->switchToPublic();
    $role = Role::factory()->create();
    $role->permissions()->attach(Permission::query()->where('slug', 'waste_records.view_own')->value('id'));
    $operator = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $role->id,
        'is_super_admin' => false, 'email_verified_at' => now(),
    ]);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $own = balanceRecord(['created_by' => $operator->id, 'record_number' => 'OWN-REPORT']);
    balanceHauling($own, ['quantity' => 20]);
    balanceRecord(['record_number' => 'OTHER-REPORT']);
    $this->actingAs($operator)->get(balanceUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('report.rows', 1)->where('report.rows.0.closing', 80)
        ->where('report.rows.0.references.0.record_number', 'OWN-REPORT'));
    $csv = $this->get(balanceUrl('export.csv'))->assertOk()->streamedContent();
    expect($csv)->toContain('OWN-REPORT')->not->toContain('OTHER-REPORT');
});

test('balance cannot read another tenant through organization parameters', function () {
    balanceRecord(['record_number' => 'FIRST-TENANT']);
    $this->tenantService->switchToPublic();
    $other = Organization::factory()->create();
    $this->tenantService->createSchema($other->schema_name);
    $this->tenantService->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
    $operator = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::query()->where('slug', 'operator')->value('id'),
        'is_super_admin' => false, 'email_verified_at' => now(),
    ]);
    $this->tenantService->switchToSchema($other->schema_name);
    balanceRecord(['record_number' => 'SECOND-TENANT']);
    $this->get(balanceUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('report.rows', 1)->where('report.rows.0.references.0.record_number', 'FIRST-TENANT'));
    $this->get(balanceUrl('export.csv'))->assertOk();
    $this->actingAs($operator)->get(balanceUrl('index', ['organization_id' => $other->id]))->assertForbidden();
    $this->get(balanceUrl('export.csv', ['organization_id' => $other->id]))->assertForbidden();
});

test('balance rejects reversed or malformed dates and enforces permissions for page and export', function () {
    foreach (['index', 'export.csv'] as $route) {
        $this->getJson(balanceUrl($route, ['start_date' => 'bad']))->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->getJson(balanceUrl($route, ['end_date' => '2026-09-30']))->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }
    $this->tenantService->switchToPublic();
    $user = User::factory()->create([
        'organization_id' => $this->organization->id, 'role_id' => Role::factory()->create()->id,
        'is_super_admin' => false, 'email_verified_at' => now(),
    ]);
    foreach (['index', 'export.csv'] as $route) {
        $this->actingAs($user)->get(balanceUrl($route))->assertForbidden();
    }
});

test('balance CSV includes period basis numeric negative values quoted cells and neutralized formulas', function () {
    $type = WasteType::factory()->create(['name' => '=DANGEROUS()', 'code' => '+CODE']);
    $record = balanceRecord([
        'waste_type_id' => $type->id, 'record_number' => '@REF',
        'quantity' => 5, 'date' => '2026-10-01', 'source' => "=SUM(1,2)\nnew line",
    ]);
    balanceHauling($record, ['quantity' => 10]);
    $response = $this->get(balanceUrl('export.csv'))->assertOk();
    $csv = $response->streamedContent();
    $file = fopen('php://temp', 'r+');
    fwrite($file, substr($csv, 3));
    rewind($file);
    $rows = [];
    while (($row = fgetcsv($file, null, ',', '"', '')) !== false) {
        $rows[] = $row;
    }
    fclose($file);
    expect($rows[0])->toBe(['Neraca Operasional Limbah', '2026-10-01', '2026-10-31'])
        ->and($rows[1][1])->toBe(WasteBalanceService::BASIS)
        ->and($rows[3])->toBe(["'=DANGEROUS()", "'+CODE", 'kg', '0', '5', '10', '-5', '1'])
        ->and($rows[9][0])->toBe("'@REF")
        ->and($rows[9][2])->toBe("'=SUM(1,2)\nnew line")
        ->and($rows[9][8])->toBe('-5');
});

test('empty balance has no invented zero total or anomaly', function () {
    $this->get(balanceUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('report.rows', [])->where('report.totals', []));
});
