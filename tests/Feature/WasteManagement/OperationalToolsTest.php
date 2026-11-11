<?php

use App\Models\FabaInternalDestination;
use App\Models\FabaPurpose;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantService;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->organization = Organization::factory()->create();
    $this->tenantService = app(TenantService::class);
    $this->tenantService->createSchema($this->organization->schema_name);
    $this->tenantService->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToPublic();
    $this->admin = User::factory()->create(['organization_id' => $this->organization->id, 'is_super_admin' => true, 'email_verified_at' => now()]);
    $this->actingAs($this->admin);
    $this->tenantService->switchToSchema($this->organization->schema_name);
});
afterEach(function () {
    $this->tenantService->switchToPublic();
});
function toolUrl($test, string $name, array $params = []): string
{
    return route('waste-management.'.$name, [...$params, 'organization_id' => $test->organization->id]);
}
test('master tools create edit deactivate preserve identity and forbid deletion', function (string $kind, string $model) {
    $url = toolUrl($this, 'master-data.faba.store', ['kind' => $kind]);
    $payload = ['name' => 'Master Baru', 'slug' => 'master-baru', 'description' => 'Catatan', 'is_active' => true];
    $this->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $item = $model::where('slug', 'master-baru')->firstOrFail();
    $this->post($url, $payload)->assertSessionHasErrors('slug');
    $edit = toolUrl($this, 'master-data.faba.update', ['kind' => $kind, 'record' => $item->id]);
    $this->put($edit, [...$payload, 'slug' => 'changed'])->assertSessionHasErrors('slug');
    $this->put($edit, [...$payload, 'name' => 'Diubah', 'is_active' => false])->assertRedirect()->assertSessionHasNoErrors();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect($item->fresh()->name)->toBe('Diubah')->and($item->fresh()->slug)->toBe('master-baru')->and($item->fresh()->is_active)->toBeFalse();
    $this->delete($edit)->assertStatus(405);
    $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component('waste-management/master-data/FabaMasters')->has('items', 1));
})->with([['destinations', FabaInternalDestination::class], ['purposes', FabaPurpose::class]]);
test('new permissions require explicit role assignment and operator rights do not grant master tools', function () {
    $this->seed(\Database\Seeders\OperationalToolsPermissionsSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionsSeeder::class);
    foreach (['admin', 'supervisor', 'operator', 'super_admin'] as $slug) {
        expect(Role::where('slug', $slug)->firstOrFail()->permissions()->whereIn('slug', \Database\Seeders\OperationalToolsPermissionsSeeder::SLUGS)->count())->toBe(0);
    }
    $role = Role::where('slug', 'operator')->firstOrFail();
    $user = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $role->id, 'is_super_admin' => false, 'email_verified_at' => now()]);
    $this->actingAs($user)->get(toolUrl($this, 'master-data.faba.index', ['kind' => 'purposes']))->assertForbidden();
    $role->permissions()->syncWithoutDetaching(Permission::where('slug', 'faba_master.view')->pluck('id'));
    $this->actingAs($user->fresh())->get(toolUrl($this, 'master-data.faba.index', ['kind' => 'purposes']))->assertOk();
    $this->post(toolUrl($this, 'master-data.faba.store', ['kind' => 'purposes']), ['name' => 'No', 'slug' => 'no', 'is_active' => true])->assertForbidden();
});
test('master edit cannot access another tenant identifier', function () {
    $other = Organization::factory()->create();
    $this->tenantService->createSchema($other->schema_name);
    $this->tenantService->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToSchema($other->schema_name);
    $foreign = FabaPurpose::factory()->create();
    $this->put(toolUrl($this, 'master-data.faba.update', ['kind' => 'purposes', 'record' => $foreign->id]), ['name' => 'Forbidden', 'is_active' => false])->assertNotFound();
});

test('vendor expiry is date inclusive and unknown remains unknown', function () {
    \Illuminate\Support\Carbon::setTestNow('2026-11-11 23:59:59');
    $vendor = new \App\Models\Vendor(['license_expiry_date' => '2026-11-11']);
    expect($vendor->licenseExpiryStatus())->toBe('not_expired')->and($vendor->hasValidLicense())->toBeTrue()
        ->and($vendor->licenseExpiryStatus('2026-11-12'))->toBe('expired')
        ->and($vendor->licenseExpiryStatus('2026-11-10'))->toBe('not_expired');
    $vendor->license_expiry_date = null;
    expect($vendor->licenseExpiryStatus())->toBe('unknown')->and($vendor->hasValidLicense())->toBeFalse();
    \Illuminate\Support\Carbon::setTestNow();
});
test('expired vendor can be deactivated without changing expiry and altered dates keep existing validation', function () {
    $vendor = \App\Models\Vendor::factory()->create(['license_expiry_date' => '2020-01-01']);
    $url = toolUrl($this, 'master-data.vendors.update', ['vendor' => $vendor->id]);
    $payload = ['name' => $vendor->name, 'code' => $vendor->code, 'license_number' => $vendor->license_number, 'license_expiry_date' => '2020-01-01', 'is_active' => false];
    $this->put($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect($vendor->fresh()->is_active)->toBeFalse()->and($vendor->fresh()->license_expiry_date->format('Y-m-d'))->toBe('2020-01-01');
    $this->put($url, [...$payload, 'license_expiry_date' => '2020-01-02'])->assertSessionHasErrors('license_expiry_date');
});

function auditRow(string $reference, string $module = 'waste_records'): \App\Models\OperationalAuditLog
{
    return \App\Models\OperationalAuditLog::create(['module' => $module, 'reference_id' => $reference, 'action' => 'fixture_change', 'actor_id' => auth()->id(), 'details' => ['before' => ['notes' => 'before', 'attachment_path' => 'private/secret.pdf'], 'after' => ['notes' => 'after', 'source' => 'Unit A', 'attachment_path' => 'private/new.pdf', 'secret' => 'redact', 'description' => ['nested' => 'secret']]]]);
}
test('audit viewer paginates filters and redacts legacy private paths and unexpected structures', function () {
    $record = \App\Models\WasteRecord::factory()->draft()->create(['created_by' => $this->admin->id]);
    for ($i = 0; $i < 27; $i++) {
        auditRow($record->id);
    }
    $url = toolUrl($this, 'audit.index', ['action' => 'fixture_change', 'reference_id' => $record->id]);
    $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('entries', 25)->where('total', 27)->where('hasMore', true)->where('entries.0.after.notes', 'after')->missing('entries.0.after.attachment_path')->missing('entries.0.before.attachment_path')->missing('entries.0.after.secret')->missing('entries.0.after.description'));
    $this->get($url.'&page=2')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('entries', 2)->where('hasMore', false));
    $this->get(toolUrl($this, 'audit.index', ['module' => 'waste_haulings', 'action' => 'fixture_change']))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('entries', 0)->where('total', 0));
});
test('audit requires independent permission and preserves own versus all module visibility', function () {
    $this->seed(\Database\Seeders\OperationalToolsPermissionsSeeder::class);
    $role = Role::factory()->create();
    $user = User::factory()->create(['role_id' => $role->id, 'organization_id' => $this->organization->id, 'is_super_admin' => false, 'email_verified_at' => now()]);
    $own = \App\Models\WasteRecord::factory()->draft()->create(['created_by' => $user->id]);
    $other = \App\Models\WasteRecord::factory()->draft()->create(['created_by' => $this->admin->id]);
    auditRow($own->id);
    auditRow($other->id);
    $url = toolUrl($this, 'audit.index', ['action' => 'fixture_change']);
    $this->actingAs($user)->get($url)->assertForbidden();
    $role->permissions()->sync(Permission::whereIn('slug', ['operational_audit.view'])->pluck('id'));
    $this->actingAs($user->fresh())->get($url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('entries', 0));
    $role->permissions()->sync(Permission::whereIn('slug', ['operational_audit.view', 'waste_records.view_own'])->pluck('id'));
    $this->actingAs($user->fresh())->get($url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('entries', 1)->where('entries.0.reference_id', $own->id));
    $role->permissions()->sync(Permission::whereIn('slug', ['operational_audit.view', 'waste_records.view_all'])->pluck('id'));
    $this->actingAs($user->fresh())->get($url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('entries', 2));
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $own->delete();
    $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('entries', 1));
});
test('audit tenant boundaries and absent tables fail closed without provisioning', function () {
    $other = Organization::factory()->create();
    $this->tenantService->createSchema($other->schema_name);
    $this->tenantService->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToSchema($other->schema_name);
    $record = \App\Models\WasteRecord::factory()->draft()->create();
    auditRow($record->id);
    $this->get(toolUrl($this, 'audit.index', ['action' => 'fixture_change']))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('entries', 0));
    $this->tenantService->switchToSchema($this->organization->schema_name);
    \Illuminate\Support\Facades\Schema::drop('operational_audit_logs');
    $this->get(toolUrl($this, 'audit.index'))->assertStatus(503);
});
test('audit captures descriptive expiry and decision fields for new events', function () {
    $record = \App\Models\WasteRecord::factory()->draft()->create(['source' => 'Unit A', 'description' => 'Description', 'notes' => 'Note']);
    $record->update(['notes' => 'New note', 'approval_notes' => 'Approved reason', 'rejection_reason' => 'Rejected reason']);
    $log = \App\Models\OperationalAuditLog::where('reference_id', $record->id)->latest('created_at')->latest('id')->firstOrFail();
    expect($log->details['after'])->toHaveKeys(['source', 'description', 'notes', 'expiry_date', 'approval_notes', 'rejection_reason']);
    expect($log->details['after']['notes'])->toBe('New note')->and($log->details['before']['notes'])->toBe('Note');
});

test('FABA edit includes only current inactive references alongside active choices', function () {
    $destination = FabaInternalDestination::factory()->create(['is_active' => false]);
    $purpose = FabaPurpose::factory()->create(['is_active' => false]);
    $vendor = \App\Models\Vendor::factory()->create(['is_active' => false, 'license_expiry_date' => '2020-01-01']);
    FabaPurpose::factory()->create(['is_active' => false]);
    $movement = \App\Models\FabaMovement::factory()->create(['movement_type' => 'utilization_internal', 'approval_status' => 'pending_approval', 'created_by' => $this->admin->id, 'internal_destination_id' => $destination->id, 'purpose_id' => $purpose->id, 'vendor_id' => $vendor->id]);
    $this->get(toolUrl($this, 'faba.utilization.edit', ['utilization' => $movement->id]))->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->has('internalDestinations', 1)->where('internalDestinations.0.id', $destination->id)->has('purposes', 1)->where('purposes.0.id', $purpose->id)->where('vendors.0.id', $vendor->id)->has('vendors.0.license_expiry_date'));
});
test('explicit opt-in grants survive normal role seeding without granting other roles', function () {
    $this->seed(\Database\Seeders\OperationalToolsPermissionsSeeder::class);
    $role = Role::where('slug', 'supervisor')->firstOrFail();
    $role->permissions()->syncWithoutDetaching(Permission::where('slug', 'operational_audit.view')->pluck('id'));
    $this->seed(\Database\Seeders\RolePermissionsSeeder::class);
    expect($role->hasPermission('operational_audit.view'))->toBeTrue()
        ->and(Role::where('slug', 'operator')->firstOrFail()->hasPermission('operational_audit.view'))->toBeFalse()
        ->and(Role::where('slug', 'admin')->firstOrFail()->hasPermission('operational_audit.view'))->toBeFalse();
});
test('audit date filters accept independent bounds and reject inverted ranges', function () {
    $this->get(toolUrl($this, 'audit.index', ['to' => '2026-11-11']))->assertOk();
    $this->get(toolUrl($this, 'audit.index', ['from' => '2026-11-12', 'to' => '2026-11-11']))->assertSessionHasErrors('to');
});

test('vendor listing retains legacy counts and date payload while adding canonical informational day', function () {
    $vendor = \App\Models\Vendor::factory()->create(['license_expiry_date' => '2026-11-11']);
    $this->get(toolUrl($this, 'master-data.vendors.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->where('vendors.0.id', $vendor->id)->where('vendors.0.transportations_count', 0)->where('vendors.0.faba_movements_count', 0)->has('vendors.0.license_expiry_date')->where('vendors.0.license_expiry_day', '2026-11-11')->has('licenseReferenceDate'));
});
test('hauling audit respects hauling ownership independently of waste record ownership', function () {
    $this->seed(\Database\Seeders\OperationalToolsPermissionsSeeder::class);
    $role = Role::factory()->create();
    $role->permissions()->sync(Permission::whereIn('slug', ['operational_audit.view', 'waste_hauling.view_own'])->pluck('id'));
    $user = User::factory()->create(['role_id' => $role->id, 'organization_id' => $this->organization->id, 'is_super_admin' => false, 'email_verified_at' => now()]);
    $record = \App\Models\WasteRecord::factory()->approved()->create(['created_by' => $this->admin->id, 'quantity' => 100]);
    $own = \App\Models\WasteHauling::factory()->create(['waste_record_id' => $record->id, 'created_by' => $user->id, 'quantity' => 1]);
    $other = \App\Models\WasteHauling::factory()->create(['waste_record_id' => $record->id, 'created_by' => $this->admin->id, 'quantity' => 1]);
    auditRow($own->id, 'waste_haulings');
    auditRow($other->id, 'waste_haulings');
    auditRow($record->id);
    $this->actingAs($user)->get(toolUrl($this, 'audit.index', ['action' => 'fixture_change']))
        ->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->has('entries', 1)->where('entries.0.reference_id', $own->id));
});
