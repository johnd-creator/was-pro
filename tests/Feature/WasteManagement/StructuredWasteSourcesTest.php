<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteRecord;
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

test('structured source is independent and historical raw source is preserved', function () {
    $source = \App\Models\WasteSource::create(['name' => 'Sumber uji', 'is_active' => true]);
    $record = WasteRecord::factory()->draft()->create(['created_by' => $this->owner->id, 'source' => 'Historis']);
    $category = $record->wasteType->category_id;
    $this->actingAs($this->owner)->put(route('waste-management.records.update', $record), [
        'date' => $record->date->toDateString(), 'waste_type_id' => $record->waste_type_id,
        'quantity' => $record->quantity, 'unit' => $record->unit, 'source_id' => $source->id,
    ])->assertSessionHasNoErrors()->assertRedirect();
    expect($record->fresh()->source_id)->toBe($source->id)->and($record->fresh()->source)->toBe('Historis')
        ->and($record->fresh()->wasteType->category_id)->toBe($category);
    $this->get(route('waste-management.records.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('wasteRecords.0.source_name', 'Sumber uji'));
});
test('inactive and nonexistent sources cannot be newly assigned', function () {
    $source = \App\Models\WasteSource::create(['name' => 'Nonaktif', 'is_active' => false]);
    $record = WasteRecord::factory()->draft()->create(['created_by' => $this->owner->id]);
    foreach ([$source->id, (string) \Illuminate\Support\Str::uuid()] as $id) {
        $this->actingAs($this->owner)->put(route('waste-management.records.update', $record), [
            'date' => $record->date->toDateString(), 'waste_type_id' => $record->waste_type_id,
            'quantity' => $record->quantity, 'unit' => $record->unit, 'source_id' => $id,
        ])->assertSessionHasErrors('source_id');
    }
    expect($record->fresh()->source_id)->toBeNull();
});
test('record user cannot manage source master without master permission', function () {
    $this->actingAs($this->owner)->post(route('waste-management.master-data.sources.store'), ['name' => 'Ditolak', 'is_active' => true])->assertForbidden();
});
test('unmigrated tenant can list and edit records without source column', function () {
    \Illuminate\Support\Facades\Schema::table('waste_records', fn ($table) => $table->dropConstrainedForeignId('source_id'));
    \Illuminate\Support\Facades\Schema::drop('waste_sources');
    $record = WasteRecord::factory()->draft()->create(['created_by' => $this->owner->id]);
    $this->actingAs($this->owner)->get(route('waste-management.records.index'))->assertOk();
    $this->put(route('waste-management.records.update', $record), [
        'date' => $record->date->toDateString(), 'waste_type_id' => $record->waste_type_id,
        'quantity' => $record->quantity, 'unit' => $record->unit, 'source_id' => '',
    ])->assertSessionHasNoErrors()->assertRedirect();
});

test('source from another tenant is rejected and hidden from options', function () {
    $tenants = app(TenantService::class);
    $other = Organization::factory()->create();
    $tenants->createSchema($other->schema_name);
    $tenants->switchToSchema($other->schema_name);
    Artisan::call('migrate', ['--path' => 'database/migrations/tenant', '--force' => true]);
    $foreign = \App\Models\WasteSource::create(['name' => 'Sumber tenant lain', 'is_active' => true]);
    $tenants->switchToSchema($this->organization->schema_name);
    $record = WasteRecord::factory()->draft()->create(['created_by' => $this->owner->id]);
    $this->actingAs($this->owner)->put(route('waste-management.records.update', $record), [
        'date' => $record->date->toDateString(), 'waste_type_id' => $record->waste_type_id,
        'quantity' => $record->quantity, 'unit' => $record->unit, 'source_id' => $foreign->id,
    ])->assertSessionHasErrors('source_id');
    $this->get(route('waste-management.records.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('wasteSources', 0));
});

test('authorized master editor can create and deactivate a source', function () {
    $role = Role::findOrFail($this->owner->role_id);
    $role->permissions()->syncWithoutDetaching(Permission::whereIn('slug', ['waste_categories.view', 'waste_categories.create', 'waste_categories.edit'])->pluck('id'));
    $this->actingAs($this->owner->fresh())->post(route('waste-management.master-data.sources.store'), ['name' => 'Sumber terkendali', 'is_active' => true])->assertSessionHasNoErrors()->assertRedirect();
    $source = \App\Models\WasteSource::where('name', 'Sumber terkendali')->firstOrFail();
    $this->put(route('waste-management.master-data.sources.update', $source->id), ['name' => 'Sumber terkendali', 'is_active' => false])->assertSessionHasNoErrors()->assertRedirect();
    expect($source->fresh()->is_active)->toBeFalse();
});
