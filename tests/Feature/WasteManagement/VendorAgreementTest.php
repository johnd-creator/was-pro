<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorAgreement;
use App\Services\TenantService;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->organization = Organization::factory()->create();
    $this->tenants = app(TenantService::class);
    $this->tenants->createSchema($this->organization->schema_name);
    $this->tenants->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenants->switchToPublic();
    $this->admin = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => Role::where('slug', 'super_admin')->value('id'), 'is_super_admin' => true, 'email_verified_at' => now()]);
    $this->actingAs($this->admin);
    $this->tenants->switchToSchema($this->organization->schema_name);
    $this->vendor = Vendor::factory()->create(['is_active' => true, 'is_transporter' => true, 'is_processor' => true]);
    $this->payload = ['transporter_vendor_id' => $this->vendor->id, 'receiver_vendor_id' => $this->vendor->id, 'document_number' => 'SYNTHETIC-MOU-001', 'valid_from' => '2026-01-01', 'valid_until' => '2026-12-31', 'waste_classification' => 'b3', 'document_status' => 'recorded'];
});
afterEach(function () {
    $this->tenants->switchToPublic();
});
function agreementRoute($test, string $action, ?VendorAgreement $agreement = null): string
{
    return route('waste-management.master-data.vendor-agreements.'.$action, ['organization_id' => $test->organization->id, ...($agreement ? ['agreement' => $agreement->id] : [])]);
}
test('one company supports multiple roles and multiple counterparties without changing legacy vendors', function () {
    $legacy = Vendor::factory()->create();
    expect($legacy->fresh()->is_transporter)->toBeNull()->and($legacy->fresh()->is_processor)->toBeNull()->and($legacy->fresh()->is_utilizer)->toBeNull();
    $this->post(agreementRoute($this, 'store'), $this->payload)->assertRedirect()->assertSessionHasNoErrors();
    $this->tenants->switchToSchema($this->organization->schema_name);
    $receiver = Vendor::factory()->create(['is_active' => true, 'is_utilizer' => true]);
    $this->post(agreementRoute($this, 'store'), [...$this->payload, 'receiver_vendor_id' => $receiver->id])->assertSessionHasNoErrors();
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(VendorAgreement::count())->toBe(2);
    $this->post(agreementRoute($this, 'store'), $this->payload)->assertSessionHasErrors('document_number');
});
test('agreement rejects invalid dates unassigned roles and missing dates for recorded status', function () {
    $this->post(agreementRoute($this, 'store'), [...$this->payload, 'valid_until' => '2025-12-31'])->assertSessionHasErrors('valid_until');
    $this->post(agreementRoute($this, 'store'), [...$this->payload, 'valid_from' => null, 'valid_until' => null])->assertSessionHasErrors(['valid_from', 'valid_until']);
    $this->tenants->switchToSchema($this->organization->schema_name);
    $unassigned = Vendor::factory()->create(['is_active' => true]);
    $this->post(agreementRoute($this, 'store'), [...$this->payload, 'receiver_vendor_id' => $unassigned->id])->assertSessionHasErrors('receiver_vendor_id');
    $this->post(agreementRoute($this, 'store'), [...$this->payload, 'valid_from' => null, 'valid_until' => null, 'document_status' => 'draft'])->assertSessionHasNoErrors();
});
test('vendor and agreement IDs from another tenant cannot be used or updated', function () {
    $this->tenants->switchToPublic();
    $other = Organization::factory()->create();
    $this->tenants->createSchema($other->schema_name);
    $this->tenants->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
    $this->tenants->switchToSchema($other->schema_name);
    $foreign = Vendor::factory()->create(['is_active' => true, 'is_transporter' => true, 'is_processor' => true]);
    $agreement = VendorAgreement::create([...$this->payload, 'transporter_vendor_id' => $foreign->id, 'receiver_vendor_id' => $foreign->id]);
    $this->post(agreementRoute($this, 'store'), [...$this->payload, 'receiver_vendor_id' => $foreign->id])->assertSessionHasErrors('receiver_vendor_id');
    $this->put(agreementRoute($this, 'update', $agreement), $this->payload)->assertNotFound();
});
test('operator without vendor permissions cannot create or update agreements', function () {
    $agreement = VendorAgreement::create($this->payload);
    $this->tenants->switchToPublic();
    $user = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => Role::where('slug', 'operator')->value('id'), 'is_super_admin' => false, 'email_verified_at' => now()]);
    $this->actingAs($user)->post(agreementRoute($this, 'store'), $this->payload)->assertForbidden();
    $this->put(agreementRoute($this, 'update', $agreement), $this->payload)->assertForbidden();
});
test('period status is inclusive unknown without dates and never claims legal validity', function () {
    $this->travelTo(now()->setDate(2026, 12, 31)->startOfDay());
    $agreement = new VendorAgreement($this->payload);
    expect($agreement->periodStatus())->toBe('Dalam rentang tanggal');
    $this->travel(1)->days();
    expect($agreement->periodStatus())->toBe('Berakhir');
    $agreement->valid_until = null;
    expect($agreement->periodStatus())->toBe('Belum lengkap');
});
test('existing agreement preserves inactive parties and prevents vendor deletion', function () {
    $agreement = VendorAgreement::create($this->payload);
    $this->vendor->update(['is_active' => false]);
    $this->put(agreementRoute($this, 'update', $agreement), [...$this->payload, 'document_status' => 'revoked'])->assertSessionHasNoErrors();
    $this->delete(route('waste-management.master-data.vendors.destroy', ['vendor' => $this->vendor->id, 'organization_id' => $this->organization->id]))->assertSessionHas('error');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect($this->vendor->fresh()->deleted_at)->toBeNull();
});
test('vendor screen remains usable before additive migration is installed', function () {
    $migration = require database_path('migrations/tenant/2026_11_13_095204_add_vendor_roles_and_agreements.php');
    $this->vendor->update(['is_transporter' => null, 'is_processor' => null, 'is_utilizer' => null]);
    $migration->down();
    $this->get(route('waste-management.master-data.vendors.index', ['organization_id' => $this->organization->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('agreementsReady', false)->has('agreements', 0));
    $this->post(agreementRoute($this, 'store'), $this->payload)->assertStatus(503);
});
test('vendor updates preserve roles when legacy clients omit them', function () {
    $this->put(route('waste-management.master-data.vendors.update', ['vendor' => $this->vendor->id, 'organization_id' => $this->organization->id]), ['name' => $this->vendor->name, 'code' => $this->vendor->code, 'is_active' => true])->assertSessionHasNoErrors();
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect($this->vendor->fresh()->is_transporter)->toBeTrue()->and($this->vendor->fresh()->is_processor)->toBeTrue();
});

test('rollback refuses to erase stored roles or agreement history', function () {
    $migration = require database_path('migrations/tenant/2026_11_13_095204_add_vendor_roles_and_agreements.php');
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    $agreement = VendorAgreement::create($this->payload);
    $this->vendor->update(['is_transporter' => null, 'is_processor' => null, 'is_utilizer' => null]);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    expect(VendorAgreement::find($agreement->id))->not->toBeNull();
});
