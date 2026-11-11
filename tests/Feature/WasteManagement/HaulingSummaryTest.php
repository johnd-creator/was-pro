<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
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
        'is_super_admin' => true,
        'email_verified_at' => now(),
    ]);
    $this->actingAs($this->admin);
    $this->tenantService->switchToSchema($this->organization->schema_name);
});

afterEach(function () {
    $this->tenantService->switchToPublic();
});

test('hauling summary keeps quantities separate by unit', function () {
    foreach ([['kg', 100], ['liter', 20], ['kg', 25], ['ton', 2]] as [$unit, $quantity]) {
        WasteRecord::factory()->approved()->create([
            'quantity' => $quantity, 'unit' => $unit, 'created_by' => $this->admin->id,
        ]);
    }

    $this->get(route('waste-management.haulings.index', ['organization_id' => $this->organization->id]))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/haulings/Index')
        ->where('stats.total_records', 4)
        ->where('stats.remaining_quantities', [
            ['unit' => 'kg', 'quantity' => 125],
            ['unit' => 'liter', 'quantity' => 20],
            ['unit' => 'ton', 'quantity' => 2],
        ])
        ->missing('stats.total_remaining_quantity'));
});

test('hauling summary pending count follows visible record scope', function () {
    $this->tenantService->switchToPublic();
    $operator = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::query()->where('slug', 'operator')->value('id'),
        'is_super_admin' => false,
        'email_verified_at' => now(),
    ]);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    foreach ([$operator, $this->admin] as $owner) {
        $record = WasteRecord::factory()->approved()->create([
            'quantity' => 100, 'unit' => 'kg', 'created_by' => $owner->id,
        ]);
        WasteHauling::factory()->pendingApproval()->create([
            'waste_record_id' => $record->id, 'quantity' => 10, 'unit' => 'kg', 'created_by' => $owner->id,
        ]);
    }

    $this->actingAs($operator)->get(route('waste-management.haulings.index'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('stats.total_records', 1)
        ->where('stats.pending_requests', 1));
});
