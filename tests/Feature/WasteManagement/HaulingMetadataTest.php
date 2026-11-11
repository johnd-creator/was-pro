<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use App\Services\HaulingMetadata;
use App\Services\TenantService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    metadataToken($this, $this->admin);
    $this->tenantService->switchToSchema($this->organization->schema_name);
});

afterEach(function () {
    $this->tenantService->switchToPublic();
});

function metadataToken($test, User $user): void
{
    $token = \Illuminate\Support\Str::random(40);
    \App\Models\ApiToken::create(['user_id' => $user->id, 'name' => 'Metadata test', 'token' => hash('sha256', $token)]);
    $test->withToken($token);
}

function metadataPayload($test): array
{
    $record = WasteRecord::factory()->approved()->create([
        'quantity' => 100, 'unit' => 'kg', 'created_by' => $test->admin->id,
    ]);

    return ['waste_record_id' => $record->id, 'quantity' => 10, 'hauling_date' => now()->toDateString()];
}

test('optional metadata roundtrips through web and api with private evidence and unchanged stock', function (bool $api) {
    Storage::fake('local');
    $payload = metadataPayload($this);
    $vendor = Vendor::factory()->create();
    $metadata = ['vendor_id' => $vendor->id, 'vehicle_number' => 'B 1234 CD', 'driver_name' => 'Budi', 'document_number' => 'MAN-001', 'destination' => 'Gudang penerima', 'receiver' => 'Petugas'];
    $route = $api ? 'api.v1.waste-haulings.store' : 'waste-management.haulings.store';
    $url = route($route, ['organization_id' => $this->organization->id]);
    $response = $this->post($url, [...$payload, ...$metadata, 'attachment' => UploadedFile::fake()->image('bukti.png')], $api ? ['Accept' => 'application/json'] : []);
    $api ? $response->assertCreated() : $response->assertRedirect();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $hauling = WasteHauling::firstOrFail();
    foreach ($metadata as $field => $value) {
        expect($hauling->$field)->toBe($value);
    }
    Storage::disk('local')->assertExists($hauling->attachment_path);
    $record = WasteRecord::findOrFail($payload['waste_record_id']);
    expect($record->getRemainingQuantity())->toBe(100.0)
        ->and($record->getReservedHaulingQuantity())->toBe(10.0);
    $show = route($api ? 'api.v1.waste-haulings.show' : 'waste-management.haulings.show', ['wasteHauling' => $hauling->id, 'organization_id' => $this->organization->id]);
    $response = $api ? $this->getJson($show) : $this->get($show);
    $response->assertOk();
    if ($api) {
        $response->assertJsonPath('data.document_number', 'MAN-001')->assertJsonPath('data.has_attachment', true)->assertJsonMissingPath('data.attachment_path');
    } else {
        $response->assertInertia(fn (AssertableInertia $page) => $page->where('hauling.document_number', 'MAN-001')->where('hauling.has_attachment', true)->missing('hauling.attachment_path'));
    }
    $this->get(route($api ? 'api.v1.waste-haulings.attachment' : 'waste-management.haulings.attachment', ['wasteHauling' => $hauling->id, 'organization_id' => $this->organization->id]))
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
})->with([false, true]);

test('old payload and reads remain compatible before metadata migration and migration preserves legacy rows', function () {
    $payload = metadataPayload($this);
    $migration = require database_path('migrations/tenant/2026_11_11_000001_add_operational_metadata_to_waste_haulings.php');
    $migration->down();
    expect(app(HaulingMetadata::class)->available())->toBeFalse();
    $url = route('api.v1.waste-haulings.store', ['organization_id' => $this->organization->id]);
    $this->postJson($url, $payload)->assertCreated()->assertJsonPath('data.metadata_available', false);
    $this->postJson($url, [...$payload, 'document_number' => 'DOC-NEW'])->assertUnprocessable()->assertJsonValidationErrors('document_number');
    $this->get(route('waste-management.haulings.create', ['waste_record' => $payload['waste_record_id'], 'organization_id' => $this->organization->id]))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('metadataAvailable', false)->has('vendors', 0));
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $migration->up();
    $hauling = WasteHauling::firstOrFail();
    expect($hauling->quantity)->toBe('10.00')->and($hauling->document_number)->toBeNull()->and($hauling->status)->toBe('pending_approval');
    $migration->down();
    expect(WasteHauling::count())->toBe(1);
});

test('metadata rejects invalid vendor oversized fields and unsafe attachment', function () {
    $payload = metadataPayload($this);
    $this->postJson(route('api.v1.waste-haulings.store', ['organization_id' => $this->organization->id]), [
        ...$payload, 'vendor_id' => fake()->uuid(), 'vehicle_number' => str_repeat('x', 51), 'driver_name' => str_repeat('x', 256),
        'document_number' => str_repeat('x', 256), 'destination' => str_repeat('x', 256),
        'receiver' => str_repeat('x', 256), 'attachment' => UploadedFile::fake()->create('bad.html', 1, 'text/html'),
    ])->assertUnprocessable()->assertJsonValidationErrors(['vendor_id', 'vehicle_number', 'driver_name', 'document_number', 'destination', 'receiver', 'attachment']);
});

test('vendor references including deleted haulings prevent vendor deletion', function () {
    $payload = metadataPayload($this);
    $vendor = Vendor::factory()->create();
    $hauling = WasteHauling::factory()->create([...$payload, 'vendor_id' => $vendor->id, 'created_by' => $this->admin->id]);
    $hauling->delete();
    $this->delete(route('waste-management.master-data.vendors.destroy', ['vendor' => $vendor->id, 'organization_id' => $this->organization->id]))
        ->assertRedirect()->assertSessionHas('error');
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(Vendor::find($vendor->id))->not->toBeNull();
});

test('private evidence denies other owner and other tenant on web and api', function () {
    Storage::fake('local');
    $payload = metadataPayload($this);
    $this->post(route('waste-management.haulings.store', ['organization_id' => $this->organization->id]), [...$payload, 'attachment' => UploadedFile::fake()->image('bukti.png')])->assertRedirect();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $id = WasteHauling::firstOrFail()->id;
    $this->tenantService->switchToPublic();
    $operator = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => Role::where('slug', 'operator')->value('id'), 'is_super_admin' => false, 'email_verified_at' => now()]);
    $this->actingAs($operator);
    metadataToken($this, $operator);
    foreach (['waste-management.haulings.attachment', 'api.v1.waste-haulings.attachment'] as $route) {
        $this->getJson(route($route, ['wasteHauling' => $id]))->assertForbidden();
    }
    $this->tenantService->switchToPublic();
    $other = Organization::factory()->create();
    $this->tenantService->createSchema($other->schema_name);
    $this->tenantService->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToPublic();
    $otherAdmin = User::factory()->create(['organization_id' => $other->id, 'role_id' => $this->admin->role_id, 'is_super_admin' => true, 'email_verified_at' => now()]);
    $this->actingAs($otherAdmin);
    metadataToken($this, $otherAdmin);
    foreach (['waste-management.haulings.attachment', 'api.v1.waste-haulings.attachment'] as $route) {
        $this->getJson(route($route, ['wasteHauling' => $id, 'organization_id' => $other->id]))->assertNotFound();
    }
});

test('failed audit rolls back uploaded private evidence', function () {
    Storage::fake('local');
    $payload = metadataPayload($this);
    $this->mock(\App\Services\OperationalAuditService::class)->shouldReceive('record')->andThrow(new RuntimeException('test audit failure'));
    $this->postJson(route('api.v1.waste-haulings.store', ['organization_id' => $this->organization->id]), [...$payload, 'attachment' => UploadedFile::fake()->image('bukti.png')])->assertServerError();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(WasteHauling::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('metadata does not change approval stock accounting and is audited', function () {
    $payload = metadataPayload($this);
    $this->postJson(route('api.v1.waste-haulings.store'), [...$payload, 'document_number' => 'AUDIT-MANIFEST'])->assertCreated();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $hauling = WasteHauling::firstOrFail();
    expect(\App\Models\OperationalAuditLog::where('reference_id', $hauling->id)->firstOrFail()->details['after']['document_number'])->toBe('AUDIT-MANIFEST');
    $this->tenantService->switchToPublic();
    $approver = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $this->admin->role_id, 'is_super_admin' => true]);
    $this->actingAs($approver);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $hauling->approve($approver->id);
    $record = WasteRecord::findOrFail($payload['waste_record_id']);
    expect($record->getApprovedHauledQuantity())->toBe(10.0)
        ->and($record->getRemainingQuantity())->toBe(90.0)
        ->and($record->getReservedHaulingQuantity())->toBe(10.0);
});

test('legacy vendor deletion does not query the absent metadata column', function () {
    $vendor = Vendor::factory()->create();
    $migration = require database_path('migrations/tenant/2026_11_11_000001_add_operational_metadata_to_waste_haulings.php');
    $migration->down();
    $this->delete(route('waste-management.master-data.vendors.destroy', ['vendor' => $vendor->id, 'organization_id' => $this->organization->id]))->assertRedirect()->assertSessionHasNoErrors();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(Vendor::find($vendor->id))->toBeNull();
});

test('metadata cannot bypass record ownership on web or api', function (bool $api) {
    $payload = metadataPayload($this);
    $this->tenantService->switchToPublic();
    $operator = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => Role::where('slug', 'operator')->value('id'), 'is_super_admin' => false, 'email_verified_at' => now()]);
    $this->actingAs($operator);
    metadataToken($this, $operator);
    $this->postJson(route($api ? 'api.v1.waste-haulings.store' : 'waste-management.haulings.store'), [...$payload, 'document_number' => 'DENIED'])->assertForbidden();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(WasteHauling::count())->toBe(0);
})->with([false, true]);

test('vendor belonging to another tenant cannot be submitted', function () {
    $payload = metadataPayload($this);
    $this->tenantService->switchToPublic();
    $other = Organization::factory()->create();
    $this->tenantService->createSchema($other->schema_name);
    $this->tenantService->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToSchema($other->schema_name);
    $vendor = Vendor::factory()->create();
    $this->postJson(route('api.v1.waste-haulings.store'), [...$payload, 'vendor_id' => $vendor->id])->assertUnprocessable()->assertJsonValidationErrors('vendor_id');
});

test('main hauling index exposes metadata capability and tenant vendor choices only after migration', function () {
    $vendor = Vendor::factory()->create(['is_active' => true]);
    Vendor::factory()->create(['is_active' => false]);
    $url = route('waste-management.haulings.index', ['organization_id' => $this->organization->id]);
    $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('metadataAvailable', true)
        ->has('vendors', 1)
        ->where('vendors.0.id', $vendor->id));
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $migration = require database_path('migrations/tenant/2026_11_11_000001_add_operational_metadata_to_waste_haulings.php');
    $migration->down();
    $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('metadataAvailable', false)
        ->has('vendors', 0));
});

test('serializing eager loaded hauling metadata does not add queries per row', function () {
    $payload = metadataPayload($this);
    foreach (['Vendor A', 'Vendor B', 'Vendor C'] as $name) {
        $vendor = Vendor::factory()->create(['name' => $name]);
        WasteHauling::factory()->create([...$payload, 'vendor_id' => $vendor->id, 'created_by' => $this->admin->id]);
    }
    $haulings = WasteHauling::with('vendor')->get();
    $connection = \Illuminate\Support\Facades\DB::connection();
    $connection->enableQueryLog();
    $connection->flushQueryLog();
    try {
        $service = app(HaulingMetadata::class);
        expect($service->serialize($haulings[0])['vendor_name'])->not->toBeNull();
        $firstQueryCount = count($connection->getQueryLog());
        foreach ($haulings->skip(1) as $hauling) {
            expect($service->serialize($hauling)['vendor_name'])->not->toBeNull();
        }
        expect(count($connection->getQueryLog()))->toBe($firstQueryCount);
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }
});
