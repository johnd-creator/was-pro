<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use App\Services\TenantService;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 11, 12)->setTime(12, 0));
    config(['operational-inbox.enabled' => false]);
    $this->organization = Organization::factory()->create();
    $this->tenants = app(TenantService::class);
    $this->tenants->createSchema($this->organization->schema_name);
    $this->tenants->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->role = Role::factory()->create();
    $this->role->permissions()->sync(Permission::whereIn('slug', ['waste_records.view_all', 'waste_records.view_own'])->pluck('id'));
    $this->user = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $this->role->id, 'is_super_admin' => false]);
    $this->actingAs($this->user);
    $this->tenants->switchToSchema($this->organization->schema_name);
});

afterEach(function () {
    $this->tenants->switchToPublic();
    $this->travelBack();
});

function alertRecord($test, string $expiry, array $attributes = []): WasteRecord
{
    $test->tenants->switchToSchema($test->organization->schema_name);
    $record = WasteRecord::factory()->approved()->create([
        'date' => '2026-10-01', 'quantity' => 100, 'unit' => 'kg', 'created_by' => $test->user->id, ...$attributes,
    ]);
    $record->forceFill(['expiry_date' => $expiry])->saveQuietly();

    return $record;
}

function alertUrl($test, string $name, array $parameters = []): string
{
    return route('waste-management.'.$name, ['organization_id' => $test->organization->id, 'month' => '2026-11', ...$parameters]);
}

test('storage alerts use remaining snapshot stock and are shared without historical inbox', function () {
    $partial = alertRecord($this, '2026-11-10');
    WasteHauling::factory()->approved()->create(['waste_record_id' => $partial->id, 'hauling_date' => '2026-11-05', 'quantity' => 40, 'unit' => 'kg']);
    $full = alertRecord($this, '2026-11-10');
    WasteHauling::factory()->approved()->create(['waste_record_id' => $full->id, 'hauling_date' => '2026-11-05', 'quantity' => 100, 'unit' => 'kg']);
    $today = alertRecord($this, '2026-11-12');
    $futureHauling = alertRecord($this, '2026-11-10');
    WasteHauling::factory()->approved()->create(['waste_record_id' => $futureHauling->id, 'hauling_date' => '2026-11-20', 'quantity' => 100, 'unit' => 'kg']);
    $this->get(alertUrl($this, 'records.index', ['attention' => 'expired']))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('wasteRecords', 2)->where('recordSummary.total', 4)
            ->where('recordSummary.expired', 2)->where('notificationSummary.expired_waste_count', 2)
            ->where('notificationSummary.expiring_soon_waste_count', 1)->where('filters.as_of', '2026-11-12')
            ->where('wasteRecords', fn ($rows) => collect($rows)->firstWhere('id', $partial->id)['remaining_quantity'] == 60));
    $this->get(alertUrl($this, 'notifications.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('available', false)->has('notifications', 0)->has('currentAlerts', 2)
        ->where('notificationSummary.total_count', 3)->where('currentAlerts.0.source_url', alertUrl($this, 'records.index', ['attention' => 'expired'])));
    $this->get(alertUrl($this, 'records.index', ['month' => '2026-10', 'attention' => 'expired']))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('wasteRecords', 0)->where('notificationSummary.expired_waste_count', 0)->where('filters.as_of', '2026-10-31'));
});

test('current alerts respect own permissions revocation and organization isolation', function () {
    alertRecord($this, '2026-11-10');
    $other = User::factory()->create(['organization_id' => $this->organization->id]);
    alertRecord($this, '2026-11-10', ['created_by' => $other->id]);
    $this->get(alertUrl($this, 'notifications.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('notificationSummary.expired_waste_count', 2));
    $this->role->permissions()->sync(Permission::where('slug', 'waste_records.view_own')->pluck('id'));
    $this->user->unsetRelation('role');
    $this->get(alertUrl($this, 'notifications.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('notificationSummary.expired_waste_count', 1));
    $anotherOrganization = Organization::factory()->create();
    $this->get(alertUrl($this, 'notifications.index', ['organization_id' => $anotherOrganization->id]))->assertForbidden();
    $this->role->permissions()->detach();
    $this->user->unsetRelation('role');
    $this->get(alertUrl($this, 'notifications.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('notificationSummary.total_count', 0)->has('currentAlerts', 0));
});

test('pending notifications are not truncated and match the selected month list not drafts', function () {
    WasteRecord::factory()->pendingReview()->count(7)->create(['date' => '2026-11-01', 'created_by' => $this->user->id]);
    WasteRecord::factory()->pendingReview()->create(['date' => '2026-10-01', 'created_by' => $this->user->id]);
    WasteRecord::factory()->draft()->create(['date' => '2026-11-01', 'created_by' => $this->user->id]);
    WasteRecord::factory()->pendingReview()->create(['date' => '2026-11-20', 'created_by' => $this->user->id]);
    $this->get(alertUrl($this, 'records.index', ['attention' => 'pending']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('wasteRecords', 7)->where('recordSummary.pending', 7)->where('recordSummary.draft', 1)->where('recordSummary.total', 9)
        ->where('notificationSummary.pending_waste_approvals_count', 7)->where('notificationSummary.total_count', 7));
});

test('current alerts remain available when the historical notification table is absent', function () {
    config(['operational-inbox.enabled' => true]);
    $record = alertRecord($this, '2026-11-10');
    $migration = require database_path('migrations/tenant/2026_11_11_061041_create_operational_notifications_table.php');
    $migration->down();
    $this->get(alertUrl($this, 'notifications.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('available', false)->where('notificationSummary.expired_waste_count', 1)->has('currentAlerts', 1));
});

test('filtered CSV matches the remaining snapshot records', function () {
    $partial = alertRecord($this, '2026-11-10');
    WasteHauling::factory()->approved()->create(['waste_record_id' => $partial->id, 'hauling_date' => '2026-11-05', 'quantity' => 40, 'unit' => 'kg']);
    $full = alertRecord($this, '2026-11-10');
    WasteHauling::factory()->approved()->create(['waste_record_id' => $full->id, 'hauling_date' => '2026-11-05', 'quantity' => 100, 'unit' => 'kg']);
    $laterHauling = alertRecord($this, '2026-11-10');
    WasteHauling::factory()->approved()->create(['waste_record_id' => $laterHauling->id, 'hauling_date' => '2026-11-20', 'quantity' => 100, 'unit' => 'kg']);
    $response = $this->get(alertUrl($this, 'records.export.csv', ['attention' => 'expired']));
    $response->assertOk();
    expect($response->streamedContent())->toContain($partial->record_number)->toContain($laterHauling->record_number)->not->toContain($full->record_number);
    $legacy = $this->get(route('waste-management.records.export.csv', [
        'organization_id' => $this->organization->id, 'storage_status' => 'expired', 'storage_date' => '2026-11-12',
    ]))->assertOk();
    expect($legacy->streamedContent())->toContain($partial->record_number)->not->toContain($laterHauling->record_number)->not->toContain($full->record_number);
});

test('shared alerts preserve historical FABA numeric query and route periods', function () {
    $this->role->permissions()->sync(Permission::whereIn('slug', ['waste_records.view_all', 'faba_recaps.view', 'faba_approvals.view'])->pluck('id'));
    $this->user->unsetRelation('role');
    alertRecord($this, '2026-11-10');
    foreach ([
        route('waste-management.faba.recaps.monthly', ['year' => 2026, 'month' => 10, 'organization_id' => $this->organization->id]),
        route('waste-management.faba.approvals.review', [2026, 10, 'organization_id' => $this->organization->id]),
    ] as $url) {
        $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('notificationContext.month', '2026-10')->where('notificationContext.as_of', '2026-10-31')
            ->where('notificationSummary.expired_waste_count', 0));
    }
});

test('shared alerts retain ready modules when another module table is unavailable', function (string $missingModule) {
    $this->role->permissions()->sync(Permission::whereIn('slug', ['waste_records.view_all', 'faba_recaps.view'])->pluck('id'));
    $this->user->unsetRelation('role');
    alertRecord($this, '2026-11-10');
    config(['faba.tps_capacity.materials' => ['fly_ash' => 0, 'bottom_ash' => 0]]);
    $table = $missingModule === 'faba' ? 'faba_movements' : 'waste_records';
    \Illuminate\Support\Facades\Schema::rename($this->organization->schema_name.'.'.$table, 'unavailable_'.$table);
    $this->get(alertUrl($this, 'notifications.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('notificationSummary.expired_waste_count', $missingModule === 'faba' ? 1 : 0)
        ->where('notificationSummary.faba_warnings_count', $missingModule === 'faba' ? 0 : 1)
        ->where('notificationSummary.total_count', 1)->has('currentAlerts', 1));
})->with(['waste', 'faba']);
