<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteCategory;
use App\Models\WasteRecord;
use App\Models\WasteType;
use App\Services\TenantService;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $tenants = app(TenantService::class);
    $tenants->createSchema($this->organization->schema_name);
    $tenants->switchToSchema($this->organization->schema_name);
    Artisan::call('migrate', ['--path' => 'database/migrations/tenant', '--force' => true]);
    $role = Role::factory()->create();
    $role->permissions()->sync(Permission::whereIn('slug', ['waste_records.view_own', 'waste_records.create', 'waste_records.edit_own', 'waste_types.view', 'waste_types.create', 'waste_types.edit'])->pluck('id'));
    $this->owner = User::factory()->create(['name' => 'Pencatat Aktual', 'organization_id' => $this->organization->id, 'role_id' => $role->id]);
});

test('record index includes the actual creator name and preserves own scope', function () {
    $record = WasteRecord::factory()->draft()->create(['created_by' => $this->owner->id]);
    $other = User::factory()->create(['organization_id' => $this->organization->id]);
    WasteRecord::factory()->draft()->create(['created_by' => $other->id]);
    $this->actingAs($this->owner)->get(route('waste-management.records.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('wasteRecords', 1)
            ->where('wasteRecords.0.id', $record->id)
            ->where('wasteRecords.0.created_by.name', 'Pencatat Aktual'));
});

test('simplified record update preserves historical source and description', function () {
    $record = WasteRecord::factory()->draft()->create(['created_by' => $this->owner->id, 'source' => 'Lokasi historis', 'description' => 'Deskripsi historis']);
    $this->actingAs($this->owner)->put(route('waste-management.records.update', $record), [
        'date' => $record->date->toDateString(), 'waste_type_id' => $record->waste_type_id,
        'quantity' => $record->quantity, 'unit' => $record->unit, 'notes' => 'Catatan baru',
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($record->fresh()->source)->toBe('Lokasi historis')
        ->and($record->fresh()->description)->toBe('Deskripsi historis')
        ->and($record->fresh()->notes)->toBe('Catatan baru');
});

test('waste type can be created without a characteristic after nullable migration', function () {
    $category = WasteCategory::factory()->create();
    $this->actingAs($this->owner)->post(route('waste-management.master-data.types.store'), [
        'name' => 'Jenis B3 tanpa karakteristik', 'code' => 'B3-OPTIONAL', 'category_id' => $category->id,
        'storage_period_days' => 90, 'transport_cost' => 0, 'is_active' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect(WasteType::where('code', 'B3-OPTIONAL')->firstOrFail()->characteristic_id)->toBeNull();
});

test('editing waste type without characteristic preserves its old classification', function () {
    $type = WasteType::factory()->create();
    $original = $type->characteristic_id;
    $this->actingAs($this->owner)->put(route('waste-management.master-data.types.update', $type), [
        'name' => 'Nama diperbarui', 'code' => $type->code, 'category_id' => $type->category_id,
        'storage_period_days' => 90, 'transport_cost' => 0, 'is_active' => true,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($type->fresh()->characteristic_id)->toBe($original)->and($type->fresh()->name)->toBe('Nama diperbarui');
});

test('unmigrated tenant receives a clear validation error instead of a database error', function () {
    \Illuminate\Support\Facades\Schema::table('waste_types', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->uuid('characteristic_id')->nullable(false)->change();
    });
    $category = WasteCategory::factory()->create();
    $this->actingAs($this->owner)->post(route('waste-management.master-data.types.store'), [
        'name' => 'Jenis belum dimigrasi', 'code' => 'B3-UNMIGRATED', 'category_id' => $category->id,
        'storage_period_days' => 90, 'transport_cost' => 0, 'is_active' => true,
    ])->assertSessionHasErrors('name');
    expect(WasteType::where('code', 'B3-UNMIGRATED')->exists())->toBeFalse();
});
