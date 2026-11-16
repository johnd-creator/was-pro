<?php

use App\Models\ApiToken;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteCategory;
use App\Models\WasteCharacteristic;
use App\Models\WasteRecord;
use App\Models\WasteSource;
use App\Models\WasteType;
use App\Services\TenantService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenantService = app(TenantService::class);

    $this->organization = Organization::factory()->create([
        'code' => 'APIWASTE',
        'schema_name' => 'tenant_api_waste',
    ]);

    if (! $this->tenantService->schemaExists($this->organization->schema_name)) {
        $this->tenantService->createSchema($this->organization->schema_name);
    }

    $this->tenantService->switchToSchema($this->organization->schema_name);
    $this->tenantService->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToPublic();

    $operatorRole = Role::query()->firstOrCreate(
        ['slug' => 'operator'],
        [
            'name' => 'Operator',
            'description' => 'Operator role',
            'level' => 1,
            'is_active' => true,
        ],
    );
    $supervisorRole = Role::query()->firstOrCreate(
        ['slug' => 'supervisor'],
        [
            'name' => 'Supervisor',
            'description' => 'Supervisor role',
            'level' => 2,
            'is_active' => true,
        ],
    );

    $permissions = collect([
        'waste_records.view_own' => ['name' => 'View Own Waste Records', 'module' => 'waste_records'],
        'waste_records.view_all' => ['name' => 'View All Waste Records', 'module' => 'waste_records'],
        'waste_records.create' => ['name' => 'Create Waste Records', 'module' => 'waste_records'],
        'waste_records.edit_own' => ['name' => 'Edit Own Waste Records', 'module' => 'waste_records'],
        'waste_records.edit_all' => ['name' => 'Edit All Waste Records', 'module' => 'waste_records'],
        'waste_records.delete' => ['name' => 'Delete Waste Records', 'module' => 'waste_records'],
        'waste_records.submit' => ['name' => 'Submit Waste Records', 'module' => 'waste_records'],
        'waste_records.approve' => ['name' => 'Approve Waste Records', 'module' => 'waste_records'],
        'waste_records.reject' => ['name' => 'Reject Waste Records', 'module' => 'waste_records'],
    ])->map(function (array $definition, string $slug) {
        return Permission::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $definition['name'],
                'module' => $definition['module'],
                'description' => $definition['name'],
                'is_active' => true,
            ],
        )->id;
    });

    $operatorRole->permissions()->syncWithoutDetaching([
        $permissions['waste_records.view_own'],
        $permissions['waste_records.create'],
        $permissions['waste_records.edit_own'],
        $permissions['waste_records.delete'],
        $permissions['waste_records.submit'],
    ]);
    $supervisorRole->permissions()->syncWithoutDetaching([
        $permissions['waste_records.view_all'],
        $permissions['waste_records.approve'],
        $permissions['waste_records.reject'],
    ]);

    $this->operator = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $operatorRole->id,
    ]);
    $this->supervisor = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => $supervisorRole->id,
    ]);

    $this->operatorToken = 'operator-api-token';
    ApiToken::query()->create([
        'user_id' => $this->operator->id,
        'name' => 'operator-device',
        'token' => hash('sha256', $this->operatorToken),
        'expires_at' => now()->addDay(),
    ]);

    $this->supervisorToken = 'supervisor-api-token';
    ApiToken::query()->create([
        'user_id' => $this->supervisor->id,
        'name' => 'supervisor-device',
        'token' => hash('sha256', $this->supervisorToken),
        'expires_at' => now()->addDay(),
    ]);

    $this->tenantService->switchToSchema($this->organization->schema_name);
    $category = WasteCategory::factory()->create();
    $characteristic = WasteCharacteristic::factory()->create();
    $this->wasteType = WasteType::factory()->create([
        'category_id' => $category->id,
        'characteristic_id' => $characteristic->id,
        'is_active' => true,
    ]);
    $this->tenantService->switchToPublic();
});

test('operator can create and view own waste records via api', function () {
    $response = $this->withToken($this->operatorToken)->postJson('/api/v1/waste-records', [
        'date' => now()->toDateString(),
        'waste_type_id' => $this->wasteType->id,
        'quantity' => 25.5,
        'unit' => 'kg',
        'source' => 'Warehouse',
        'description' => 'Packaging waste',
        'notes' => 'Need pickup tomorrow',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonFragment(['update'])
        ->assertJsonFragment(['submit']);

    $recordId = $response->json('data.id');

    $detail = $this->withToken($this->operatorToken)
        ->getJson('/api/v1/waste-records/'.$recordId);

    $detail->assertSuccessful()
        ->assertJsonPath('data.created_by.id', $this->operator->id)
        ->assertJsonPath('data.waste_type.id', $this->wasteType->id);
});

test('operator without view all only receives own records via api', function () {
    $this->operator->role->permissions()->detach(Permission::where('slug', 'waste_records.view_all')->value('id'));
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $ownRecord = WasteRecord::factory()->draft()->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    WasteRecord::factory()->draft()->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->supervisor->id,
        'updated_by' => $this->supervisor->id,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->withToken($this->operatorToken)
        ->getJson('/api/v1/waste-records');

    $response->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $ownRecord->id);
});

test('operator can submit record and supervisor can approve it via api', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $record = WasteRecord::factory()->draft()->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    $this->tenantService->switchToPublic();

    $submitResponse = $this->withToken($this->operatorToken)
        ->postJson('/api/v1/waste-records/'.$record->id.'/submit');

    $submitResponse->assertSuccessful()
        ->assertJsonPath('data.status', 'pending_review');

    $approveResponse = $this->withToken($this->supervisorToken)
        ->postJson('/api/v1/waste-records/'.$record->id.'/approve', [
            'approval_notes' => 'Approved from mobile api',
        ]);

    $approveResponse->assertSuccessful()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.approved_by.id', $this->supervisor->id);
});

test('supervisor can reject pending record and operator can return it to draft via api', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $record = WasteRecord::factory()->pendingReview()->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    $this->tenantService->switchToPublic();

    $rejectResponse = $this->withToken($this->supervisorToken)
        ->postJson('/api/v1/waste-records/'.$record->id.'/reject', [
            'rejection_reason' => 'Data quantity needs correction first.',
        ]);

    $rejectResponse->assertSuccessful()
        ->assertJsonPath('data.status', 'rejected');

    $returnResponse = $this->withToken($this->operatorToken)
        ->postJson('/api/v1/waste-records/'.$record->id.'/return-to-draft');

    $returnResponse->assertSuccessful()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonFragment(['submit']);
});

test('operator cannot approve waste record via api', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $record = WasteRecord::factory()->pendingReview()->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
    ]);
    $this->tenantService->switchToPublic();

    $response = $this->withToken($this->operatorToken)
        ->postJson('/api/v1/waste-records/'.$record->id.'/approve', [
            'approval_notes' => 'Should fail',
        ]);

    $response->assertForbidden()
        ->assertJsonPath('error_code', 'FORBIDDEN');
});
test('api creates and returns a structured source without replacing historical text', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $source = WasteSource::query()->create(['name' => 'Pemeliharaan', 'is_active' => true]);
    $this->tenantService->switchToPublic();

    $response = $this->withToken($this->operatorToken)->postJson('/api/v1/waste-records', [
        'date' => now()->toDateString(),
        'waste_type_id' => $this->wasteType->id,
        'quantity' => 25.5,
        'unit' => 'kg',
        'source_id' => $source->id,
        'source' => 'Historis gudang',
        'description' => 'Catatan asli',
    ])->assertCreated();

    $recordId = $response->json('data.id');
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $record = WasteRecord::query()->findOrFail($recordId);
    expect($record->source_id)->toBe($source->id)
        ->and($record->source)->toBe('Historis gudang')
        ->and($record->description)->toBe('Catatan asli')
        ->and($record->waste_type_id)->toBe($this->wasteType->id);

    $response->assertJsonPath('data.source_id', $source->id);
    $this->withToken($this->operatorToken)->getJson('/api/v1/waste-records/'.$recordId)
        ->assertSuccessful()->assertJsonPath('data.source_id', $source->id);
    $this->withToken($this->operatorToken)->getJson('/api/v1/waste-records')
        ->assertSuccessful()->assertJsonPath('data.0.source_id', $source->id);
});

test('api preserves omitted source fields and supports reassignment and explicit clearing', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $original = WasteSource::query()->create(['name' => 'Operasi', 'is_active' => true]);
    $replacement = WasteSource::query()->create(['name' => 'Laboratorium', 'is_active' => true]);
    $record = WasteRecord::factory()->draft()->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
        'source_id' => $original->id,
        'source' => 'Teks sumber historis',
        'description' => 'Deskripsi historis',
    ]);
    $this->tenantService->switchToPublic();
    $payload = [
        'date' => $record->date->toDateString(),
        'waste_type_id' => $record->waste_type_id,
        'quantity' => $record->quantity,
        'unit' => $record->unit,
    ];

    foreach ([[], ['source_id' => $replacement->id], ['source_id' => null]] as $change) {
        $expectedSource = array_key_exists('source_id', $change) ? $change['source_id'] : $original->id;
        $response = $this->withToken($this->operatorToken)
            ->putJson('/api/v1/waste-records/'.$record->id, [...$payload, ...$change])
            ->assertSuccessful();

        $this->tenantService->switchToSchema($this->organization->schema_name);
        expect($record->fresh()->source_id)->toBe($expectedSource)
            ->and($record->fresh()->source)->toBe('Teks sumber historis')
            ->and($record->fresh()->description)->toBe('Deskripsi historis');
        $response->assertJsonPath('data.source_id', $expectedSource);
    }

    $this->withToken($this->operatorToken)->putJson('/api/v1/waste-records/'.$record->id, [
        ...$payload, 'source' => null, 'description' => null,
    ])->assertSuccessful();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect($record->fresh()->source)->toBeNull()->and($record->fresh()->description)->toBeNull();
});

test('api rejects unavailable sources on create and update without changing the record', function (string $kind) {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $original = WasteSource::query()->create(['name' => 'Sumber awal', 'is_active' => true]);
    $record = WasteRecord::factory()->draft()->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
        'source_id' => $original->id,
        'source' => 'Historis',
    ]);

    if ($kind === 'foreign') {
        $other = Organization::factory()->create();
        $this->tenantService->createSchema($other->schema_name);
        $this->tenantService->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
        $this->tenantService->switchToSchema($other->schema_name);
    }

    $candidate = WasteSource::query()->create(['name' => 'Sumber ditolak', 'is_active' => $kind !== 'inactive']);
    if ($kind === 'deleted') {
        $candidate->delete();
    }
    $sourceId = match ($kind) {
        'missing' => (string) Str::uuid(),
        'malformed' => 'not-a-uuid',
        default => $candidate->id,
    };
    $this->tenantService->switchToPublic();
    $payload = [
        'date' => $record->date->toDateString(),
        'waste_type_id' => $record->waste_type_id,
        'quantity' => $record->quantity,
        'unit' => $record->unit,
        'source_id' => $sourceId,
    ];

    $this->withToken($this->operatorToken)->postJson('/api/v1/waste-records', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('source_id');
    $this->withToken($this->operatorToken)->putJson('/api/v1/waste-records/'.$record->id, $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('source_id');

    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(WasteRecord::query()->count())->toBe(1)
        ->and($record->fresh()->source_id)->toBe($original->id)
        ->and($record->fresh()->source)->toBe('Historis');
})->with(['inactive', 'deleted', 'foreign', 'missing', 'malformed']);

test('api can retain an existing inactive source', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $source = WasteSource::query()->create(['name' => 'Historis nonaktif', 'is_active' => false]);
    $record = WasteRecord::factory()->draft()->create([
        'waste_type_id' => $this->wasteType->id,
        'created_by' => $this->operator->id,
        'updated_by' => $this->operator->id,
        'source_id' => $source->id,
    ]);
    $this->tenantService->switchToPublic();

    $this->withToken($this->operatorToken)->putJson('/api/v1/waste-records/'.$record->id, [
        'date' => $record->date->toDateString(),
        'waste_type_id' => $record->waste_type_id,
        'quantity' => $record->quantity,
        'unit' => $record->unit,
        'source_id' => $source->id,
    ])->assertSuccessful()->assertJsonPath('data.source_id', $source->id);
});

test('api remains compatible with tenants without the structured source migration', function () {
    $this->tenantService->switchToSchema($this->organization->schema_name);
    Schema::table('waste_records', fn ($table) => $table->dropConstrainedForeignId('source_id'));
    Schema::drop('waste_sources');
    $this->tenantService->switchToPublic();
    $payload = [
        'date' => now()->toDateString(),
        'waste_type_id' => $this->wasteType->id,
        'quantity' => 10,
        'unit' => 'kg',
        'source_id' => '',
        'source' => 'Sumber lama',
    ];

    $response = $this->withToken($this->operatorToken)->postJson('/api/v1/waste-records', $payload)
        ->assertCreated()->assertJsonPath('data.source', 'Sumber lama');
    $id = $response->json('data.id');
    unset($payload['source']);
    $this->withToken($this->operatorToken)->putJson('/api/v1/waste-records/'.$id, $payload)
        ->assertSuccessful()->assertJsonPath('data.source', 'Sumber lama');
    $this->withToken($this->operatorToken)->getJson('/api/v1/waste-records/'.$id)->assertSuccessful();
    $this->withToken($this->operatorToken)->getJson('/api/v1/waste-records')->assertSuccessful();
});
