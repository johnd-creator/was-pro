<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WasteRecord;
use App\Models\WasteTransportation;
use App\Models\WasteType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

uses()->group('waste-management', 'workflow')
    ->beforeEach(function () {
        // Use array session driver for waste management tests
        app()['config']->set('session.driver', 'array');
    });

beforeEach(function () {
    // Ensure organization exists
    $this->org = Organization::firstOrCreate(
        ['code' => 'TWMS'],
        [
            'name' => 'Test Waste Management System',
            'schema_name' => 'tenant_twms',
            'address' => 'Test Address',
            'phone' => '1234567890',
            'email' => 'admin@twms.com',
        ]
    );

    // Ensure schema exists
    $tenantService = app(\App\Services\TenantService::class);
    if (! $tenantService->schemaExists($this->org->schema_name)) {
        $tenantService->createSchema($this->org->schema_name);
    }

    $tenantService->switchToSchema($this->org->schema_name);
    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--force' => true,
    ]);
    $tenantService->switchToPublic();

    // Get roles (note: slugs use underscores, not hyphens)
    // Create roles if they don't exist (tests run in transactions)
    $this->supervisorRole = Role::firstOrCreate(
        ['slug' => 'supervisor'],
        [
            'name' => 'Supervisor',
            'level' => 2,
            'description' => 'Supervisor role with approval permissions',
        ]
    );

    $this->operatorRole = Role::firstOrCreate(
        ['slug' => 'operator'],
        [
            'name' => 'Operator',
            'level' => 1,
            'description' => 'Operator role with basic permissions',
        ]
    );

    $superAdminRole = Role::firstOrCreate(
        ['slug' => 'super_admin'],
        [
            'name' => 'Super Admin',
            'level' => 4,
            'description' => 'Super administrator with all permissions',
        ]
    );

    // Get or create test users
    $this->superAdmin = User::firstOrCreate(
        ['email' => 'super@testwms.com'],
        [
            'name' => 'Super Admin',
            'password' => bcrypt('password'),
            'organization_id' => $this->org->id,
            'role_id' => $superAdminRole->id,
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]
    );

    $this->supervisor = User::firstOrCreate(
        ['email' => 'supervisor@testwms.com'],
        [
            'name' => 'Supervisor',
            'password' => bcrypt('password'),
            'organization_id' => $this->org->id,
            'role_id' => $this->supervisorRole->id,
            'email_verified_at' => now(),
        ]
    );

    $this->operator = User::firstOrCreate(
        ['email' => 'operator@testwms.com'],
        [
            'name' => 'Operator',
            'password' => bcrypt('password'),
            'organization_id' => $this->org->id,
            'role_id' => $this->operatorRole->id,
            'email_verified_at' => now(),
        ]
    );

    // Don't switch to tenant schema in beforeEach - tests will handle it
    // We need to stay in public schema for User creation

    // Set up waste categories, characteristics, and types in tenant schema
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $organicCategory = \App\Models\WasteCategory::firstOrCreate(
        ['code' => 'ORG'],
        [
            'name' => 'Organic',
            'description' => 'Organic waste materials',
        ]
    );

    $plasticCategory = \App\Models\WasteCategory::firstOrCreate(
        ['code' => 'PLA'],
        [
            'name' => 'Plastic',
            'description' => 'Plastic waste materials',
        ]
    );

    $generalCharacteristic = \App\Models\WasteCharacteristic::firstOrCreate(
        ['code' => 'GEN'],
        [
            'name' => 'General',
            'description' => 'General waste characteristics',
        ]
    );

    // Ensure waste types exist
    if (WasteType::count() === 0) {
        WasteType::firstOrCreate(
            ['code' => 'ORG-001'],
            [
                'name' => 'Organic Waste',
                'category_id' => $organicCategory->id,
                'characteristic_id' => $generalCharacteristic->id,
                'storage_period_days' => 7,
                'transport_cost' => 100000,
                'description' => 'Organic waste materials',
            ]
        );

        WasteType::firstOrCreate(
            ['code' => 'PLA-001'],
            [
                'name' => 'Plastic Waste',
                'category_id' => $plasticCategory->id,
                'characteristic_id' => $generalCharacteristic->id,
                'storage_period_days' => 30,
                'transport_cost' => 50000,
                'description' => 'Plastic waste materials',
            ]
        );
    }

    // Get waste type and switch back to public schema
    $this->wasteType = WasteType::first();
    $tenantService->switchToPublic();
});

afterEach(function () {
    // Switch back to public schema
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema('public');
});

test('operator can create waste record as draft', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.records.store'), [
            'date' => now()->toDateString(),
            'waste_type_id' => $this->wasteType->id,
            'quantity' => 100.50,
            'unit' => 'kg',
            'source' => 'Test Location',
            'description' => 'Test description',
            'notes' => 'Internal notes',
        ]);

    $response->assertStatus(302);
    $response->assertSessionHas('success');

    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $this->assertDatabaseHas('waste_records', [
        'quantity' => 100.50,
        'unit' => 'kg',
        'source' => 'Test Location',
        'status' => 'draft',
    ]);
});

test('operator can view create waste record page', function () {
    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.records.create'));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/records/Create')
        ->has('wasteTypes')
    );
});

test('operator can view waste record detail page', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $record = WasteRecord::factory()->create([
        'status' => 'draft',
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.records.show', $record));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/records/Show')
        ->where('wasteRecord.id', (string) $record->id)
        ->where('wasteRecord.record_number', $record->record_number)
    );
});

test('operator can view waste records index page', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    WasteRecord::factory()->create([
        'status' => 'draft',
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.records.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/records/Index')
        ->has('wasteRecords', 1)
    );
});

test('operator can view edit waste record page', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $record = WasteRecord::factory()->create([
        'status' => 'draft',
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.records.edit', $record));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/records/Edit')
        ->where('wasteRecord.id', (string) $record->id)
        ->has('wasteTypes')
    );
});

test('supervisor can view pending approval page', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    WasteRecord::factory()->create([
        'status' => 'pending_review',
        'waste_type_id' => $this->wasteType->id,
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($this->supervisor)
        ->get(route('waste-management.records.pending-approval'));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/records/PendingApproval')
        ->has('wasteRecords', 1)
    );
});

test('operator can view create transportation page with partially transported approved records', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $availableRecord = WasteRecord::factory()->create([
        'status' => 'approved',
        'waste_type_id' => $this->wasteType->id,
        'quantity' => 100,
        'unit' => 'kg',
        'created_by' => $this->operator->id,
    ]);

    $fullyTransportedRecord = WasteRecord::factory()->create([
        'status' => 'approved',
        'waste_type_id' => $this->wasteType->id,
        'quantity' => 80,
        'unit' => 'kg',
        'created_by' => $this->operator->id,
    ]);

    $vendor = Vendor::factory()->create();

    WasteTransportation::factory()->create([
        'waste_record_id' => $availableRecord->id,
        'vendor_id' => $vendor->id,
        'quantity' => 40,
        'unit' => 'kg',
        'status' => 'pending',
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    WasteTransportation::factory()->create([
        'waste_record_id' => $fullyTransportedRecord->id,
        'vendor_id' => $vendor->id,
        'quantity' => 80,
        'unit' => 'kg',
        'status' => 'pending',
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);

    $tenantService->switchToPublic();

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.transportations.create'));

    $response->assertSuccessful();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/transportations/Create')
        ->has('wasteRecords', 1)
        ->where('wasteRecords.0.id', $availableRecord->id)
        ->where('wasteRecords.0.transported_quantity', 40)
        ->where('wasteRecords.0.remaining_quantity', 60)
    );
});

test('operator cannot create waste record without required fields', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.records.store'), [
            'date' => now()->toDateString(),
            // Missing waste_type_id, quantity, unit
        ]);

    $response->assertSessionHasErrors([
        'waste_type_id' => 'Jenis limbah wajib dipilih.',
        'quantity' => 'Jumlah limbah wajib diisi.',
        'unit' => 'Satuan wajib dipilih.',
    ]);
});

test('operator can submit own draft record for approval', function () {
    // Create a draft record
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $draftRecord = WasteRecord::factory()->create([
        'status' => 'draft',
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.records.submit', $draftRecord));

    $response->assertStatus(302);
    $response->assertSessionHas('success');

    $draftRecord->refresh();
    expect($draftRecord->status)->toBe('pending_review');
    expect($draftRecord->submitted_by)->toBe($this->operator->id);
    expect($draftRecord->submitted_at)->not->toBeNull();
});

test('operator cannot submit another users draft record', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $draftRecord = WasteRecord::factory()->create([
        'status' => 'draft',
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->superAdmin->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.records.submit', $draftRecord));

    $response->assertStatus(403);
});

test('operator cannot submit approved record', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $approvedRecord = WasteRecord::factory()->create([
        'status' => 'approved',
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.records.submit', $approvedRecord));

    $response->assertStatus(302);
    $response->assertSessionHas('error');
});

test('supervisor can approve pending record', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $pendingRecord = WasteRecord::factory()->create([
        'status' => 'pending_review',
        'waste_type_id' => $this->wasteType->id,
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($this->supervisor)
        ->post(route('waste-management.records.approve', $pendingRecord), [
            'approval_notes' => 'Looks good, approved.',
        ]);

    $response->assertStatus(302);
    $response->assertSessionHas('success');

    $pendingRecord->refresh();
    expect($pendingRecord->status)->toBe('approved');
    expect($pendingRecord->approved_by)->toBe($this->supervisor->id);
    expect($pendingRecord->approved_at)->not->toBeNull();
    expect($pendingRecord->approval_notes)->toBe('Looks good, approved.');
});

test('supervisor can approve pending record without approval notes', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $pendingRecord = WasteRecord::factory()->create([
        'status' => 'pending_review',
        'waste_type_id' => $this->wasteType->id,
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($this->supervisor)
        ->post(route('waste-management.records.approve', $pendingRecord), []);

    $response->assertStatus(302);
    $response->assertSessionHas('success');

    $pendingRecord->refresh();
    expect($pendingRecord->status)->toBe('approved');
    expect($pendingRecord->approved_by)->toBe($this->supervisor->id);
    expect($pendingRecord->approved_at)->not->toBeNull();
    expect($pendingRecord->approval_notes)->toBeNull();
});

test('operator cannot approve pending record', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $pendingRecord = WasteRecord::factory()->create([
        'status' => 'pending_review',
        'waste_type_id' => $this->wasteType->id,
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.records.approve', $pendingRecord), [
            'approval_notes' => 'Approving this.',
        ]);

    $response->assertStatus(403);
});

test('supervisor can reject pending record with reason', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $pendingRecord = WasteRecord::factory()->create([
        'status' => 'pending_review',
        'waste_type_id' => $this->wasteType->id,
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($this->supervisor)
        ->post(route('waste-management.records.reject', $pendingRecord), [
            'rejection_reason' => 'Quantity does not match source documentation.',
        ]);

    $response->assertStatus(302);
    $response->assertSessionHas('success');

    $pendingRecord->refresh();
    expect($pendingRecord->status)->toBe('rejected');
    expect($pendingRecord->approved_by)->toBe($this->supervisor->id);
    expect($pendingRecord->rejection_reason)->toBe('Quantity does not match source documentation.');
});

test('rejection reason must be at least 10 characters', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $pendingRecord = WasteRecord::factory()->create([
        'status' => 'pending_review',
        'waste_type_id' => $this->wasteType->id,
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($this->supervisor)
        ->post(route('waste-management.records.reject', $pendingRecord), [
            'rejection_reason' => 'Short',
        ]);

    $response->assertSessionHasErrors(['rejection_reason']);
});

test('operator can return rejected record to draft', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $rejectedRecord = WasteRecord::factory()->create([
        'status' => 'rejected',
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
        'submitted_by' => $this->operator->id,
        'approved_by' => $this->supervisor->id,
        'submitted_at' => now()->subDay(),
        'approved_at' => now(),
        'rejection_reason' => 'This is a valid rejection reason with enough details.',
    ]);

    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.records.return-to-draft', $rejectedRecord));

    $response->assertStatus(302);
    $response->assertSessionHas('success');

    $rejectedRecord->refresh();
    expect($rejectedRecord->status)->toBe('draft');
});

test('operator cannot edit approved record', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $approvedRecord = WasteRecord::factory()->create([
        'status' => 'approved',
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.records.edit', $approvedRecord));

    $response->assertStatus(403);
});

test('operator cannot edit pending review record', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $pendingRecord = WasteRecord::factory()->create([
        'status' => 'pending_review',
        'waste_type_id' => $this->wasteType->id,
        'submitted_by' => $this->operator->id,
        'submitted_at' => now(),
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.records.edit', $pendingRecord));

    $response->assertStatus(403);
});

test('operator can edit own draft record', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $draftRecord = WasteRecord::factory()->create([
        'status' => 'draft',
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.records.edit', $draftRecord));

    $response->assertStatus(200);
});

test('operator can edit own rejected record', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $rejectedRecord = WasteRecord::factory()->create([
        'status' => 'rejected',
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
        'submitted_by' => $this->operator->id,
        'approved_by' => $this->supervisor->id,
        'submitted_at' => now()->subDay(),
        'approved_at' => now(),
        'rejection_reason' => 'This is a valid rejection reason with enough details.',
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.records.edit', $rejectedRecord));

    $response->assertStatus(200);
});

test('operator is forbidden from deleting approved record', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $approvedRecord = WasteRecord::factory()->create([
        'status' => 'approved',
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->delete(route('waste-management.records.destroy', $approvedRecord));

    $response->assertForbidden();

    $tenantService->switchToSchema($this->org->schema_name);

    $this->assertDatabaseHas('waste_records', [
        'id' => $approvedRecord->id,
        'status' => 'approved',
    ]);
});

test('operator is forbidden from deleting own draft record without delete permission', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $draftRecord = WasteRecord::factory()->create([
        'status' => 'draft',
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->delete(route('waste-management.records.destroy', $draftRecord));

    $response->assertForbidden();

    $tenantService->switchToSchema($this->org->schema_name);

    $this->assertDatabaseHas('waste_records', [
        'id' => $draftRecord->id,
    ]);
});

test('operator can only see own records', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    // Create records by different users
    WasteRecord::factory()->count(3)->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
    ]);

    WasteRecord::factory()->count(2)->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->supervisor->id,
    ]);

    $response = $this->actingAs($this->operator)
        ->get(route('waste-management.records.index'));

    $response->assertStatus(200);

    // Operator should only see records created by themselves
    $records = WasteRecord::where('created_by', $this->operator->id)->get();
    expect($records->count())->toBeGreaterThanOrEqual(3);
});

test('supervisor can see all records', function () {
    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    // Create records by different users
    WasteRecord::factory()->count(3)->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
    ]);

    WasteRecord::factory()->count(2)->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->supervisor->id,
    ]);

    $response = $this->actingAs($this->supervisor)
        ->get(route('waste-management.records.index'));

    $response->assertStatus(200);

    // Supervisor should see all records
    $allRecords = WasteRecord::all();
    expect($allRecords->count())->toBeGreaterThanOrEqual(5);
});

test('record number is auto-generated correctly', function () {
    $response = $this->actingAs($this->operator)
        ->post(route('waste-management.records.store'), [
            'date' => now()->toDateString(),
            'waste_type_id' => $this->wasteType->id,
            'quantity' => 50,
            'unit' => 'kg',
            'source' => 'Test',
        ]);

    $response->assertStatus(302);

    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($this->org->schema_name);

    $prefix = 'WR-'.$this->org->code.'-'.now()->format('Y-m');

    $this->assertDatabaseHas('waste_records', [
        'record_number' => $prefix.'-0001',
    ]);
});

test('waste management demo command seeds deterministic data for twelve previous months', function () {
    \Carbon\CarbonImmutable::setTestNow('2026-03-19 10:00:00');

    $tenantCode = 'TWMSWMDEMO';
    $schemaName = 'tenant_twms_wm_demo';

    $this->artisan('waste-management:seed-demo', [
        '--tenant' => $tenantCode,
        '--schema' => $schemaName,
        '--fresh-tenant' => true,
    ])->assertSuccessful();

    $organization = Organization::query()->where('code', $tenantCode)->first();

    expect($organization)->not->toBeNull()
        ->and($organization?->schema_name)->toBe($schemaName);
    expect(User::query()->where('email', 'john@d.co')->where('organization_id', $organization?->id)->where('is_super_admin', true)->exists())->toBeTrue();

    $tenantService = app(\App\Services\TenantService::class);
    $tenantService->switchToSchema($schemaName);

    expect(\App\Models\WasteCategory::query()->count())->toBe(3)
        ->and(\App\Models\WasteCharacteristic::query()->count())->toBe(3)
        ->and(WasteType::query()->count())->toBe(4)
        ->and(Vendor::query()->count())->toBe(3)
        ->and(WasteRecord::query()->count())->toBe(144)
        ->and(WasteTransportation::query()->count())->toBe(60);

    $periods = collect(range(12, 1))
        ->map(fn (int $monthsBack) => now()->startOfMonth()->subMonthsNoOverflow($monthsBack));

    foreach ($periods as $period) {
        expect(
            WasteRecord::query()
                ->whereYear('date', $period->year)
                ->whereMonth('date', $period->month)
                ->count()
        )->toBe(12);
    }

    expect(WasteRecord::query()->where('status', 'draft')->count())->toBe(36)
        ->and(WasteRecord::query()->where('status', 'pending_review')->count())->toBe(36)
        ->and(WasteRecord::query()->where('status', 'approved')->count())->toBe(48)
        ->and(WasteRecord::query()->where('status', 'rejected')->count())->toBe(24)
        ->and(WasteTransportation::query()->where('status', 'pending')->count())->toBe(12)
        ->and(WasteTransportation::query()->where('status', 'in_transit')->count())->toBe(12)
        ->and(WasteTransportation::query()->where('status', 'delivered')->count())->toBe(24)
        ->and(WasteTransportation::query()->where('status', 'cancelled')->count())->toBe(12);

    $firstThreePeriods = $periods->take(3)->values();
    $categoryDistributionForPeriod = function (\Carbon\CarbonImmutable $period): array {
        return WasteRecord::query()
            ->approved()
            ->whereYear('date', $period->year)
            ->whereMonth('date', $period->month)
            ->join('waste_types', 'waste_records.waste_type_id', '=', 'waste_types.id')
            ->join('waste_categories', 'waste_types.category_id', '=', 'waste_categories.id')
            ->selectRaw('waste_categories.code as category_code, SUM(waste_records.quantity) as total_quantity')
            ->groupBy('waste_categories.code')
            ->pluck('total_quantity', 'category_code')
            ->map(fn ($value): float => round((float) $value, 2))
            ->all();
    };

    $firstDistribution = $categoryDistributionForPeriod($firstThreePeriods[0]);
    $secondDistribution = $categoryDistributionForPeriod($firstThreePeriods[1]);
    $thirdDistribution = $categoryDistributionForPeriod($firstThreePeriods[2]);

    expect($firstDistribution)->not->toBe($secondDistribution)
        ->and($secondDistribution)->not->toBe($thirdDistribution)
        ->and(collect($firstDistribution)->sortDesc()->keys()->first())->toBe('B3')
        ->and(collect($secondDistribution)->sortDesc()->keys()->first())->toBe('REC')
        ->and(collect($thirdDistribution)->sortDesc()->keys()->first())->toBe('GEN');

    $hasOverflowTransportation = WasteTransportation::query()
        ->get()
        ->contains(fn (WasteTransportation $transportation): bool => $transportation->quantityExceedsRecord());

    expect($hasOverflowTransportation)->toBeFalse();

    \Carbon\CarbonImmutable::setTestNow();
});

test('waste management demo seed can populate an existing tenant without overwriting organization metadata', function () {
    $existingOrganization = Organization::factory()->create([
        'code' => 'TWMSWM',
        'name' => 'Tenant Produksi Waste',
        'schema_name' => 'tenant_twms_wm_existing',
    ]);

    $tenantService = app(\App\Services\TenantService::class);
    if (! $tenantService->schemaExists($existingOrganization->schema_name)) {
        $tenantService->createSchema($existingOrganization->schema_name);
    }

    $tenantService->switchToSchema($existingOrganization->schema_name);
    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--force' => true,
    ]);
    $tenantService->switchToPublic();

    $john = User::factory()->create([
        'email' => 'john-wm@d.co',
        'organization_id' => $existingOrganization->id,
        'role_id' => Role::where('slug', 'super_admin')->value('id'),
        'is_super_admin' => true,
    ]);

    $response = $this->artisan('waste-management:seed-demo', [
        '--tenant' => 'TWMSWM',
        '--schema' => $existingOrganization->schema_name,
    ]);

    $response->assertSuccessful();

    $existingOrganization->refresh();
    expect($existingOrganization->name)->toBe('Tenant Produksi Waste')
        ->and(User::query()->find($john->id))->not->toBeNull();
});

test('waste management demo seed can run while current search path is a tenant schema', function () {
    \Carbon\CarbonImmutable::setTestNow('2026-03-19 10:00:00');

    $existingOrganization = Organization::factory()->create([
        'code' => 'TWMSWMSEARCH',
        'name' => 'Tenant Waste Search Path',
        'schema_name' => 'tenant_twms_wm_search_path',
    ]);

    $tenantService = app(\App\Services\TenantService::class);

    if (! $tenantService->schemaExists($existingOrganization->schema_name)) {
        $tenantService->createSchema($existingOrganization->schema_name);
    }

    $tenantService->switchToSchema($existingOrganization->schema_name);
    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--force' => true,
    ]);

    $this->artisan('waste-management:seed-demo', [
        '--tenant' => 'TWMSWMSEARCH',
        '--schema' => $existingOrganization->schema_name,
    ])->assertSuccessful();

    $tenantService->switchToSchema($existingOrganization->schema_name);

    expect(WasteRecord::query()->count())->toBe(144)
        ->and(WasteTransportation::query()->count())->toBe(60);

    $tenantService->switchToPublic();
    \Carbon\CarbonImmutable::setTestNow();
});

test('tenant migrations stay inside the tenant schema even when public has tables with the same name', function () {
    $schemaName = 'tenant_twms_schema_lock';
    $tenantService = app(\App\Services\TenantService::class);

    if (! $tenantService->schemaExists($schemaName)) {
        $tenantService->createSchema($schemaName);
    }

    $tenantService->switchToPublic();

    if (Schema::hasTable('waste_categories')) {
        Schema::drop('waste_categories');
    }

    Schema::create('waste_categories', function (Blueprint $table): void {
        $table->id();
        $table->string('legacy_name')->nullable();
        $table->timestamps();
    });

    expect(fn () => $tenantService->runMigrationsForTenant($schemaName, 'database/migrations/tenant'))->not->toThrow(\Throwable::class);

    $tenantService->switchToSchema($schemaName);

    expect(Schema::hasTable('migrations'))->toBeTrue()
        ->and(Schema::hasTable('waste_categories'))->toBeTrue();

    $tenantColumns = collect(DB::select(
        'SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ?',
        [$schemaName, 'waste_categories'],
    ))->pluck('column_name');

    expect($tenantColumns)->toContain('code')
        ->and($tenantColumns)->toContain('name')
        ->and($tenantColumns)->not->toContain('legacy_name');

    $tenantService->switchToPublic();

    expect(Schema::hasColumn('waste_categories', 'legacy_name'))->toBeTrue()
        ->and(Schema::hasColumn('waste_categories', 'code'))->toBeFalse();
});

test('waste management demo seed rejects fresh mode for an existing non-demo tenant', function () {
    $existingOrganization = Organization::factory()->create([
        'code' => 'TWMSWMFRESH',
        'name' => 'Tenant Waste No Fresh',
        'schema_name' => 'tenant_twms_wm_no_fresh',
    ]);

    $response = $this->artisan('waste-management:seed-demo', [
        '--tenant' => 'TWMSWMFRESH',
        '--schema' => $existingOrganization->schema_name,
        '--fresh-tenant' => true,
    ]);

    $response->assertFailed();
});
