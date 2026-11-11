<?php

use App\Models\FabaMovement;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteRecord;
use App\Models\WasteType;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    bootstrapFeatureTestCase();
    $tenant = app(TenantService::class);
    $this->alpha = $tenant->provisionOrganization(['name' => 'Synthetic Alpha', 'code' => 'SYNALPHA', 'is_active' => true]);
    $this->beta = $tenant->provisionOrganization(['name' => 'Synthetic Beta', 'code' => 'SYNBETA', 'is_active' => true]);
    $this->operator = User::factory()->create([
        'organization_id' => $this->alpha->id,
        'role_id' => Role::where('slug', 'operator')->firstOrFail()->id,
        'is_super_admin' => false,
    ]);
    $this->superAdmin = User::factory()->create(['organization_id' => null, 'is_super_admin' => true]);
    foreach (['alpha', 'beta'] as $key) {
        $tenant->switchToSchema($this->{$key}->schema_name);
        $type = WasteType::factory()->create();
        $this->{$key.'Type'} = $type;
        $this->{$key.'Waste'} = WasteRecord::factory()->draft()->create([
            'waste_type_id' => $type->id,
            'record_number' => 'WR-SYNTHETIC-'.strtoupper($key),
            'created_by' => $this->superAdmin->id,
        ]);
        $this->{$key.'Faba'} = FabaMovement::factory()->create([
            'note' => 'FABA-SYNTHETIC-'.strtoupper($key),
            'movement_type' => FabaMovement::TYPE_PRODUCTION,
            'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
            'vendor_id' => null,
            'internal_destination_id' => null,
            'purpose_id' => null,
            'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
            'transaction_date' => '2026-03-12',
            'period_year' => 2026,
            'period_month' => 3,
            'quantity' => $key === 'alpha' ? 11 : 22,
            'created_by' => $this->superAdmin->id,
        ]);
    }
    $tenant->switchToPublic();
});

test('ordinary operator sees assigned organization and historical import author is preserved', function () {
    $this->actingAs($this->operator)->get(route('waste-management.records.index'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('organizationContext.organization.id', $this->alpha->id)
        ->where('organizationContext.can_select', false)
        ->has('organizationContext.choices', 0)
        ->has('wasteRecords', 1)->where('wasteRecords.0.id', $this->alphaWaste->id));
    $this->get(route('waste-management.faba.production.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('entries', 1)->where('entries.0.id', $this->alphaFaba->id));
    $this->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('organizationName', $this->alpha->name));
    app(TenantService::class)->switchToSchema($this->alpha->schema_name);
    expect($this->alphaWaste->fresh()->created_by)->toBe($this->superAdmin->id);
});

test('ordinary operator cannot switch organization using a filter', function (string $route) {
    $this->actingAs($this->operator)->get(route($route, ['organization_id' => $this->beta->id]))->assertForbidden();
})->with(['dashboard', 'waste-management.records.index', 'waste-management.faba.production.index', 'waste-management.faba.production.export.csv']);

test('foreign synthetic record IDs do not resolve in assigned schema', function () {
    $this->actingAs($this->operator)->get(route('waste-management.records.show', $this->betaWaste->id))->assertNotFound();
    $this->get(route('waste-management.faba.production.show', $this->betaFaba->id))->assertNotFound();
});

test('superadmin selection persists across lists forms details and exports', function () {
    $this->actingAs($this->superAdmin)->get(route('dashboard', ['organization_id' => $this->beta->id]))->assertOk();
    $this->get(route('waste-management.records.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('organizationContext.organization.id', $this->beta->id)
            ->has('wasteRecords', 1)->where('wasteRecords.0.id', $this->betaWaste->id));
    $this->get(route('waste-management.records.create'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('organizationContext.organization.id', $this->beta->id)
            ->has('wasteTypes', 1)->where('wasteTypes.0.id', $this->betaType->id));
    $this->get(route('waste-management.records.show', $this->betaWaste->id))->assertOk();
    $this->get(route('waste-management.records.show', $this->alphaWaste->id))->assertNotFound();
    $this->get(route('waste-management.faba.production.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('entries', 1)->where('entries.0.id', $this->betaFaba->id));
    $csv = $this->get(route('waste-management.faba.production.export.csv'))->assertOk()->streamedContent();
    expect($csv)->toContain('FABA-SYNTHETIC-BETA')->not->toContain('FABA-SYNTHETIC-ALPHA');
});

test('second ordinary organization has its own independent data and FABA author', function () {
    $betaOperator = User::factory()->create([
        'organization_id' => $this->beta->id,
        'role_id' => $this->operator->role_id,
        'is_super_admin' => false,
    ]);
    $this->actingAs($betaOperator)->get(route('waste-management.records.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('wasteRecords', 1)
            ->where('wasteRecords.0.id', $this->betaWaste->id)
            ->where('organizationContext.organization.id', $this->beta->id));
    $this->get(route('waste-management.records.show', $this->alphaWaste->id))->assertNotFound();
    $this->post(route('waste-management.faba.production.store'), [
        'organization_id' => $this->alpha->id,
        'transaction_date' => '2026-03-15',
        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'quantity' => 7,
        'note' => 'Synthetic beta upload',
    ])->assertSessionHasNoErrors()->assertRedirect();
    app(TenantService::class)->switchToSchema($this->beta->schema_name);
    expect(FabaMovement::where('note', 'Synthetic beta upload')->firstOrFail()->created_by)->toBe($betaOperator->id);
    app(TenantService::class)->switchToSchema($this->alpha->schema_name);
    expect(FabaMovement::where('note', 'Synthetic beta upload')->exists())->toBeFalse();
});

test('explicit tab organization takes precedence over stored selection', function () {
    $this->actingAs($this->superAdmin)->get(route('dashboard', ['organization_id' => $this->beta->id]))->assertOk();
    $this->get(route('waste-management.records.index', ['organization_id' => $this->alpha->id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('organizationContext.organization.id', $this->alpha->id)
            ->where('wasteRecords.0.id', $this->alphaWaste->id));
});

test('unassigned superadmin must select explicitly instead of inheriting first UBP', function () {
    $this->actingAs($this->superAdmin)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('OrganizationRequired')->where('organizationContext.organization', null));
    $this->post(route('waste-management.records.store'), ['organization_id' => $this->beta->id])->assertStatus(409);
});

test('ordinary account without organization has explicit safe state', function () {
    $unassigned = User::factory()->create(['organization_id' => null, 'is_super_admin' => false, 'role_id' => $this->operator->role_id]);
    $this->actingAs($unassigned)->get(route('dashboard'))->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('OrganizationRequired')->where('organizationContext.organization', null));
    $this->get(route('waste-management.records.index'))->assertForbidden();
    $this->post(route('waste-management.records.store'), [])->assertStatus(409);
});

test('superadmin creation uses selected unit prefix and actual author', function () {
    $this->actingAs($this->superAdmin)->post(route('waste-management.records.store', ['organization_id' => $this->beta->id]), [
        'date' => '2026-03-15', 'waste_type_id' => $this->betaType->id, 'quantity' => 3, 'unit' => 'kg', 'source' => 'Synthetic fixture',
    ])->assertSessionHasNoErrors()->assertRedirect();
    app(TenantService::class)->switchToSchema($this->beta->schema_name);
    $record = WasteRecord::where('source', 'Synthetic fixture')->firstOrFail();
    expect($record->record_number)->toStartWith('WR-SYNBETA-')->and($record->created_by)->toBe($this->superAdmin->id);
    app(TenantService::class)->switchToSchema($this->alpha->schema_name);
    expect(WasteRecord::where('source', 'Synthetic fixture')->exists())->toBeFalse();
});

test('body organization field cannot redirect ordinary operator write', function () {
    $this->actingAs($this->operator)->post(route('waste-management.records.store'), [
        'organization_id' => $this->beta->id, 'date' => '2026-03-15', 'waste_type_id' => $this->alphaType->id,
        'quantity' => 3, 'unit' => 'kg', 'source' => 'Synthetic own upload',
    ])->assertSessionHasNoErrors()->assertRedirect();
    app(TenantService::class)->switchToSchema($this->alpha->schema_name);
    expect(WasteRecord::where('source', 'Synthetic own upload')->firstOrFail()->created_by)->toBe($this->operator->id);
    app(TenantService::class)->switchToSchema($this->beta->schema_name);
    expect(WasteRecord::where('source', 'Synthetic own upload')->exists())->toBeFalse();
});

test('global superadmin administration remains explicitly global', function () {
    $this->actingAs($this->superAdmin)->get(route('admin.organizations.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('organizationContext.is_global', true));
});

test('superadmin stale tab creates FABA only in its explicit organization', function () {
    $this->actingAs($this->superAdmin)->get(route('dashboard', ['organization_id' => $this->beta->id]))->assertOk();
    $this->post(route('waste-management.faba.production.store', ['organization_id' => $this->alpha->id]), [
        'organization_id' => $this->beta->id, 'transaction_date' => '2026-03-15',
        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH, 'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'quantity' => 7, 'note' => 'Synthetic explicit tab upload',
    ])->assertSessionHasNoErrors()->assertRedirect();
    app(TenantService::class)->switchToSchema($this->alpha->schema_name);
    expect(FabaMovement::where('note', 'Synthetic explicit tab upload')->firstOrFail()->created_by)->toBe($this->superAdmin->id);
    app(TenantService::class)->switchToSchema($this->beta->schema_name);
    expect(FabaMovement::where('note', 'Synthetic explicit tab upload')->exists())->toBeFalse();
});

test('superadmin stale tab cannot create FABA without an explicit organization', function (string $query) {
    $this->actingAs($this->superAdmin)->get(route('dashboard', ['organization_id' => $this->beta->id]))->assertOk();
    $this->post(route('waste-management.faba.production.store').$query, [
        'organization_id' => $this->alpha->id, 'transaction_date' => '2026-03-15',
        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH, 'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'quantity' => 7, 'note' => 'Synthetic unscoped tab upload',
    ])->assertStatus(409);
    foreach ([$this->alpha, $this->beta] as $organization) {
        app(TenantService::class)->switchToSchema($organization->schema_name);
        expect(FabaMovement::count())->toBe(1)->and(FabaMovement::where('note', 'Synthetic unscoped tab upload')->exists())->toBeFalse();
    }
})->with(['missing query' => '', 'null query' => '?organization_id', 'empty query' => '?organization_id=']);

test('ordinary operator cannot forge the FABA mutation organization query', function () {
    $this->actingAs($this->operator)->post(route('waste-management.faba.production.store', ['organization_id' => $this->beta->id]), [
        'transaction_date' => '2026-03-15', 'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION, 'quantity' => 7,
    ])->assertForbidden();
    foreach ([$this->alpha, $this->beta] as $organization) {
        app(TenantService::class)->switchToSchema($organization->schema_name);
        expect(FabaMovement::count())->toBe(1);
    }
});

test('superadmin mutation rejects unavailable organization instead of using its session', function (string $state) {
    $this->actingAs($this->superAdmin)->get(route('dashboard', ['organization_id' => $this->beta->id]))->assertOk();
    $id = $this->alpha->id;
    if ($state === 'inactive') {
        $this->alpha->update(['is_active' => false]);
    } elseif ($state === 'deleted') {
        $this->alpha->delete();
    } else {
        $id = (string) Illuminate\Support\Str::uuid();
    }
    $this->postJson(route('waste-management.faba.production.store', ['organization_id' => $id]), [
        'transaction_date' => '2026-03-15', 'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION, 'quantity' => 7,
    ])->assertUnprocessable()->assertJsonValidationErrors('organization_id');
    foreach ([$this->alpha, $this->beta] as $organization) {
        app(TenantService::class)->switchToSchema($organization->schema_name);
        expect(FabaMovement::count())->toBe(1);
    }
})->with(['inactive', 'deleted', 'nonexistent']);

test('explicit organization cannot resolve another organizations named mutation resource', function (string $method, string $routeName, string $fixture) {
    $this->actingAs($this->superAdmin)->get(route('dashboard', ['organization_id' => $this->beta->id]))->assertOk();
    $resource = $this->{$fixture};
    app(TenantService::class)->switchToSchema($this->beta->schema_name);
    $before = $resource->fresh()->getAttributes();
    app(TenantService::class)->switchToPublic();
    $this->{$method}(route($routeName, [$resource->id, 'organization_id' => $this->alpha->id]), [
        'transaction_date' => '2026-03-15', 'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION, 'quantity' => 7,
    ])->assertNotFound();
    app(TenantService::class)->switchToSchema($this->beta->schema_name);
    expect($resource->fresh()->getAttributes())->toBe($before);
})->with([
    'FABA update' => ['put', 'waste-management.faba.production.update', 'betaFaba'],
    'FABA delete' => ['delete', 'waste-management.faba.production.destroy', 'betaFaba'],
    'FABA approve' => ['post', 'waste-management.faba.movements.approve', 'betaFaba'],
    'waste update' => ['put', 'waste-management.records.update', 'betaWaste'],
    'waste delete' => ['delete', 'waste-management.records.destroy', 'betaWaste'],
    'waste approve' => ['post', 'waste-management.records.approve', 'betaWaste'],
]);

test('superadmin with legacy assigned organization must explicitly select a workspace', function () {
    $this->superAdmin->update(['organization_id' => $this->alpha->id]);
    $this->actingAs($this->superAdmin)->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('OrganizationRequired')->where('organizationContext.organization', null));
    $this->get(route('profile.edit'))->assertOk();
    $this->post(route('waste-management.faba.production.store'), [])->assertStatus(409);
});

test('superadmin switches profile workspace without changing account assignment', function () {
    $originalOrganization = $this->superAdmin->organization_id;
    $this->actingAs($this->superAdmin);
    foreach ([$this->alpha, $this->beta] as $organization) {
        $this->get(route('profile.edit', ['organization_id' => $organization->id]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('settings/Profile')->where('organizationContext.organization.id', $organization->id));
        $this->get(route('dashboard'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('organizationContext.organization.id', $organization->id));
        expect($this->superAdmin->fresh()->organization_id)->toBe($originalOrganization);
    }
    $this->get(route('profile.edit').'?organization_id=')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/Profile')->where('organizationContext.organization', null));
    $this->get(route('dashboard'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('OrganizationRequired')->where('organizationContext.organization', null));
    expect($this->superAdmin->fresh()->organization_id)->toBe($originalOrganization);
});
