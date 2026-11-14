<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use App\Models\WasteType;
use App\Services\B3DashboardService;
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
        'is_super_admin' => true, 'email_verified_at' => now(),
    ]);
    $this->actingAs($this->admin);
    $this->tenantService->switchToSchema($this->organization->schema_name);
});

afterEach(function () {
    $this->tenantService->switchToPublic();
});

function b3Record(array $attributes = []): WasteRecord
{
    return WasteRecord::factory()->approved()->create([
        'created_by' => test()->admin->id, 'date' => '2026-10-01', 'quantity' => 100, 'unit' => 'kg', ...$attributes,
    ]);
}

function b3Url(array $query = []): string
{
    return route('waste-management.b3.dashboard', [
        'organization_id' => test()->organization->id, 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', ...$query,
    ]);
}

test('b3 dashboard uses approved balances with separate units and correct period boundaries', function () {
    $record = b3Record(['date' => '2026-09-01']);
    WasteHauling::factory()->approved()->create([
        'waste_record_id' => $record->id, 'created_by' => $this->admin->id,
        'hauling_date' => '2026-10-31', 'quantity' => 20, 'unit' => 'kg',
    ]);
    WasteHauling::factory()->approved()->create([
        'waste_record_id' => $record->id, 'created_by' => $this->admin->id,
        'hauling_date' => '2026-11-01', 'quantity' => 80, 'unit' => 'kg',
    ]);
    b3Record(['date' => '2026-10-01', 'quantity' => 10]);
    b3Record(['date' => '2026-10-31', 'quantity' => 5, 'unit' => 'liter']);
    b3Record(['date' => '2026-11-01', 'quantity' => 9999]);
    b3Record(['status' => 'pending_review', 'quantity' => 123]);
    $data = app(B3DashboardService::class)->dashboard($this->admin, '2026-10-01', '2026-10-31');
    expect($data['stats']['period_records'])->toBe(3)
        ->and($data['stats']['pending_records'])->toBe(1)
        ->and($data['report']['totals'])->toBe([
            ['unit' => 'kg', 'opening' => 100.0, 'incoming' => 10.0, 'outgoing' => 20.0, 'closing' => 90.0],
            ['unit' => 'liter', 'opening' => 0.0, 'incoming' => 5.0, 'outgoing' => 0.0, 'closing' => 5.0],
        ]);
});

test('b3 attention excludes fully hauled waste and handles seven day and unknown expiry boundaries', function () {
    $type = WasteType::factory()->create(['storage_period_days' => 30]);
    $expired = b3Record(['waste_type_id' => $type->id, 'date' => '2026-09-01']);
    $completed = b3Record(['waste_type_id' => $type->id, 'date' => '2026-09-01']);
    WasteHauling::factory()->approved()->create([
        'waste_record_id' => $completed->id, 'created_by' => $this->admin->id,
        'hauling_date' => '2026-10-10', 'quantity' => 100, 'unit' => 'kg',
    ]);
    b3Record(['waste_type_id' => $type->id, 'date' => '2026-10-08']);
    b3Record(['waste_type_id' => $type->id, 'date' => '2026-10-09']);
    b3Record(['waste_type_id' => WasteType::factory()->create(['storage_period_days' => 0])->id]);
    $data = app(B3DashboardService::class)->dashboard($this->admin, '2026-10-01', '2026-10-31');
    expect($data['stats']['remaining_records'])->toBe(4)
        ->and($data['stats']['expired_records'])->toBe(1)
        ->and($data['stats']['soon_records'])->toBe(1)
        ->and($data['stats']['unknown_expiry_records'])->toBe(1)
        ->and($data['stats']['attention_count'])->toBe(3)
        ->and($data['attention'][0]['id'])->toBe($expired->id)
        ->and($data['attention'][0]['input_by'])->toBe($this->admin->name);
});

test('b3 dashboard honours own record permission in all sections', function () {
    $this->tenantService->switchToPublic();
    $role = Role::factory()->create();
    $role->permissions()->attach(Permission::query()->where('slug', 'waste_records.view_own')->value('id'));
    $operator = User::factory()->create([
        'organization_id' => $this->organization->id, 'role_id' => $role->id,
        'is_super_admin' => false, 'email_verified_at' => now(),
    ]);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    b3Record(['record_number' => 'HIDDEN']);
    b3Record(['record_number' => 'OWN', 'created_by' => $operator->id]);
    $data = app(B3DashboardService::class)->dashboard($operator, '2026-10-01', '2026-10-31');
    expect($data['stats']['period_records'])->toBe(1)
        ->and($data['stats']['remaining_records'])->toBe(1)
        ->and($data['recentRecords'])->toHaveCount(1)
        ->and($data['recentRecords'][0]['record_number'])->toBe('OWN')
        ->and($data['report']['totals'][0]['incoming'])->toBe(100.0);
});

test('b3 dashboard route renders and validates invalid periods', function () {
    b3Record();
    $this->get(b3Url())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/b3/Dashboard')
        ->where('organizationName', $this->organization->name)
        ->has('report.rows', 1)->has('recentRecords', 1));
    $this->get(b3Url(['start_date' => '2026-11-01']))->assertSessionHasErrors('end_date');
});

test('b3 dashboard rejects cross organization access and users without permission', function () {
    $this->tenantService->switchToPublic();
    $other = Organization::factory()->create();
    $operator = User::factory()->create([
        'organization_id' => $this->organization->id, 'role_id' => Role::query()->where('slug', 'operator')->value('id'),
        'is_super_admin' => false, 'email_verified_at' => now(),
    ]);
    $this->actingAs($operator)->get(b3Url(['organization_id' => $other->id]))->assertForbidden();
    $emptyRole = Role::factory()->create();
    $operator->update(['role_id' => $emptyRole->id]);
    $this->actingAs($operator->fresh())->get(b3Url())->assertForbidden();
});
