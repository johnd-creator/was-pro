<?php

use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Services\TenantService;
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

test('vendor registry counts real faba references without the retired transportation model', function () {
    $vendor = Vendor::factory()->create();
    FabaMovement::factory()->create([
        'vendor_id' => $vendor->id,
        'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL,
        'stock_effect' => FabaMovement::STOCK_EFFECT_OUT,
        'approval_status' => FabaMovement::STATUS_DRAFT,
    ]);

    $this->get(route('waste-management.master-data.vendors.index', ['organization_id' => $this->organization->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('waste-management/master-data/Vendors')
            ->has('vendors', 1)
            ->where('vendors.0.id', $vendor->id)
            ->where('vendors.0.faba_movements_count', 1)
            ->where('vendors.0.transportations_count', 1));
});

test('vendor registry preserves referenced vendors and permits deleting unused vendors', function () {
    $used = Vendor::factory()->create();
    $unused = Vendor::factory()->create();
    FabaMovement::factory()->create([
        'vendor_id' => $used->id,
        'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL,
        'stock_effect' => FabaMovement::STOCK_EFFECT_OUT,
        'approval_status' => FabaMovement::STATUS_DRAFT,
    ]);

    $this->delete(route('waste-management.master-data.vendors.destroy', ['vendor' => $used, 'organization_id' => $this->organization->id]))
        ->assertRedirect()->assertSessionHas('error');
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect($used->fresh()->deleted_at)->toBeNull();
    $this->delete(route('waste-management.master-data.vendors.destroy', ['vendor' => $unused, 'organization_id' => $this->organization->id]))
        ->assertRedirect()->assertSessionHas('success');
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect($unused->fresh()->deleted_at)->not->toBeNull();
});

test('b3 csv exports loaded relationship labels', function () {
    $record = \App\Models\WasteRecord::factory()->approved()->create([
        'submitted_by' => $this->admin->id,
        'approved_by' => $this->admin->id,
    ]);
    $record->load('wasteType.category', 'wasteType.characteristic');
    $response = $this->get(route('waste-management.records.export.csv', ['organization_id' => $this->organization->id]))->assertOk();
    $rows = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));
    expect($rows[1][3])->toBe($record->wasteType->name)
        ->and($rows[1][4])->toBe($record->wasteType->category->name)
        ->and($rows[1][5])->toBe($record->wasteType->characteristic->name)
        ->and($rows[1][11])->toBe($this->admin->name)
        ->and($rows[1][13])->toBe($this->admin->name);
});

test('b3 csv honors list search storage filters and sort', function (string $filter, string $sort, array $expected) {
    $type = \App\Models\WasteType::factory()->create(['name' => 'CSV Sludge', 'storage_period_days' => 30]);
    foreach (['expired' => -1, 'today' => 0, 'seven' => 7, 'fresh' => 8, 'completed' => -2, 'pending' => -3, 'na' => null] as $name => $days) {
        $record = \App\Models\WasteRecord::factory()->approved()->create([
            'waste_type_id' => $type->id, 'record_number' => 'CSV-'.$name,
            'date' => '2026-10-01', 'quantity' => $name === 'today' ? 20 : 10,
            'source' => 'CSV filter',
            'created_at' => $name === 'pending' ? '2026-11-02 00:00:00' : '2026-11-01 00:00:00',
        ]);
        $record->updateQuietly(['expiry_date' => $days === null ? null : \Carbon\CarbonImmutable::parse('2026-11-11')->addDays($days)->toDateString()]);
        if (in_array($name, ['completed', 'pending'])) {
            \App\Models\WasteHauling::factory()->create([
                'waste_record_id' => $record->id, 'quantity' => 10,
                'status' => $name === 'completed' ? 'approved' : 'pending_approval',
            ]);
        }
    }
    \App\Models\WasteRecord::factory()->create(['source' => 'unrelated']);
    $response = $this->get(route('waste-management.records.export.csv', [
        'organization_id' => $this->organization->id, 'search' => ' csv filter ',
        'storage_status' => $filter, 'sort' => $sort, 'storage_date' => '2026-11-11',
    ]))->assertOk();
    $rows = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));
    expect(array_column(array_slice($rows, 1), 0))->toBe($expected);
})->with([
    ['expired', 'latest', ['CSV-pending', 'CSV-expired']],
    ['expiring_soon', 'quantity_high', ['CSV-today', 'CSV-seven']],
    ['expiring_soon', 'quantity_low', ['CSV-seven', 'CSV-today']],
    ['fresh', 'oldest', ['CSV-fresh']],
    ['completed', 'latest', ['CSV-completed']],
]);

test('b3 csv validates export filters', function () {
    $this->getJson(route('waste-management.records.export.csv', [
        'organization_id' => $this->organization->id,
        'storage_status' => 'invalid', 'sort' => 'invalid', 'storage_date' => 'not-a-date',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['storage_status', 'sort', 'storage_date']);
});

test('b3 csv searches the same fields as the list', function (string $field) {
    $type = \App\Models\WasteType::factory()->create(['name' => 'CSV Unique Name', 'code' => 'CSV-UNIQUE-CODE']);
    $type->category->update(['name' => 'CSV Unique Category']);
    $record = \App\Models\WasteRecord::factory()->create(['waste_type_id' => $type->id, 'record_number' => 'CSV-UNIQUE-NUMBER', 'source' => 'CSV Unique Source']);
    $response = $this->get(route('waste-management.records.export.csv', ['organization_id' => $this->organization->id, 'search' => strtoupper($field)]))->assertOk();
    expect($response->streamedContent())->toContain($record->record_number);
})->with(['CSV Unique Name', 'CSV-UNIQUE-CODE', 'CSV Unique Category', 'CSV-UNIQUE-NUMBER', 'CSV Unique Source']);

test('b3 csv sorts all matching records by list order', function (string $sort, array $expected) {
    $type = \App\Models\WasteType::factory()->create();
    foreach (['CSV-OLDER' => ['2026-10-01', 30], 'CSV-NEWER' => ['2026-10-02', 10]] as $number => [$date, $quantity]) {
        \App\Models\WasteRecord::factory()->create(['waste_type_id' => $type->id, 'record_number' => $number, 'date' => $date, 'quantity' => $quantity]);
    }
    $response = $this->get(route('waste-management.records.export.csv', ['organization_id' => $this->organization->id, 'sort' => $sort]))->assertOk();
    $rows = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));
    expect(array_column(array_slice($rows, 1), 0))->toBe($expected);
})->with([
    ['latest', ['CSV-NEWER', 'CSV-OLDER']],
    ['oldest', ['CSV-OLDER', 'CSV-NEWER']],
    ['quantity_high', ['CSV-OLDER', 'CSV-NEWER']],
    ['quantity_low', ['CSV-NEWER', 'CSV-OLDER']],
]);

test('b3 csv stays within the selected organization', function () {
    \App\Models\WasteRecord::factory()->create(['record_number' => 'CSV-SELECTED-TENANT']);
    $other = Organization::factory()->create();
    $this->tenantService->createSchema($other->schema_name);
    $this->tenantService->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToSchema($other->schema_name);
    \App\Models\WasteRecord::factory()->create(['record_number' => 'CSV-OTHER-TENANT']);
    $response = $this->get(route('waste-management.records.export.csv', ['organization_id' => $this->organization->id, 'search' => 'CSV-']))->assertOk();
    expect($response->streamedContent())->toContain('CSV-SELECTED-TENANT')->not->toContain('CSV-OTHER-TENANT');
});
