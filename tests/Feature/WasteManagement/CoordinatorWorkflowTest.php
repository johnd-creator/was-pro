<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteHaulingPlan;
use App\Models\WasteRecord;
use App\Services\TenantService;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    config(['hauling-plans.enabled' => true]);
    $this->tenants = app(TenantService::class);
    $this->organization = Organization::factory()->create();
    $this->tenants->createSchema($this->organization->schema_name);
    $this->tenants->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenants->switchToPublic();
    foreach (['operator', 'coordinator', 'supervisor'] as $slug) {
        $this->{$slug} = User::factory()->create([
            'organization_id' => $this->organization->id,
            'role_id' => Role::where('slug', $slug)->value('id'),
            'is_super_admin' => false,
            'email_verified_at' => now(),
        ]);
    }
    $this->actingAs($this->operator);
    $this->tenants->switchToSchema($this->organization->schema_name);
    $this->record = WasteRecord::factory()->approved()->create(['quantity' => 100, 'unit' => 'kg', 'created_by' => $this->operator->id]);
});

afterEach(function () {
    $this->tenants->switchToPublic();
});

test('coordinator can propose an operator record but only a different supervisor can approve reject or close plans', function () {
    $this->actingAs($this->coordinator);
    $this->get(route('waste-management.hauling-plans.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('records', 1)->where('records.0.id', $this->record->id));
    $this->post(route('waste-management.hauling-plans.store'), [
        'waste_record_id' => $this->record->id, 'planned_date' => now()->addDays(2)->toDateString(), 'quantity' => '40.00',
    ])->assertRedirect()->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    $plan = WasteHaulingPlan::firstOrFail();
    expect($plan->created_by)->toBe($this->coordinator->id)
        ->and($plan->submitted_by)->toBe($this->coordinator->id)
        ->and($plan->status)->toBe('pending_approval')
        ->and($this->record->getRemainingQuantity())->toBe(100.0);

    $this->post(route('waste-management.hauling-plans.approve', $plan))->assertForbidden();
    $this->post(route('waste-management.hauling-plans.reject', $plan), ['rejection_reason' => 'No permission'])->assertForbidden();
    $this->post(route('waste-management.hauling-plans.close', $plan), ['closure_reason' => 'No permission'])->assertForbidden();
    $this->get(route('waste-management.hauling-plans.show', $plan))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('plan.abilities.can_approve', false)->where('plan.abilities.can_reject', false)->where('plan.abilities.can_close', false));

    $this->actingAs($this->supervisor)->post(route('waste-management.hauling-plans.approve', $plan))->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect($plan->fresh()->status)->toBe('approved')
        ->and($plan->fresh()->approved_by)->toBe($this->supervisor->id)
        ->and($this->record->getRemainingQuantity())->toBe(100.0);
});

test('coordinator cannot approve waste records or administer users roles organizations and master data', function () {
    $this->actingAs($this->coordinator);
    $this->post(route('waste-management.records.approve', $this->record))->assertForbidden();
    $this->get(route('admin.users.index'))->assertForbidden();
    $this->getJson(route('admin.roles.index'))->assertForbidden();
    $this->putJson(route('admin.roles.update', $this->coordinator->role), ['permissions' => []])->assertForbidden();
    $this->post(route('admin.users.store'), [])->assertForbidden();
    $this->post(route('waste-management.master-data.types.store'), [])->assertForbidden();
    expect($this->coordinator->isSuperAdmin())->toBeFalse()
        ->and($this->coordinator->hasPermission('organizations.manage'))->toBeFalse()
        ->and($this->coordinator->hasPermission('faba_approvals.approve'))->toBeFalse();
});

test('coordinator is isolated from foreign organization records and plans even with a forged organization query', function () {
    $this->tenants->switchToPublic();
    $foreignOrganization = Organization::factory()->create();
    $this->tenants->createSchema($foreignOrganization->schema_name);
    $this->tenants->runMigrationsForTenant($foreignOrganization->schema_name, 'database/migrations/tenant');
    $foreignUser = User::factory()->create(['organization_id' => $foreignOrganization->id, 'role_id' => $this->coordinator->role_id, 'is_super_admin' => false]);
    $this->actingAs($foreignUser);
    $this->tenants->switchToSchema($foreignOrganization->schema_name);
    $foreignRecord = WasteRecord::factory()->approved()->create(['quantity' => 100, 'unit' => 'kg', 'created_by' => $foreignUser->id]);
    $foreignPlan = WasteHaulingPlan::create(['waste_record_id' => $foreignRecord->id, 'planned_date' => now()->toDateString(), 'quantity' => 10, 'unit' => 'kg', 'status' => 'pending_approval', 'created_by' => $foreignUser->id, 'submitted_by' => $foreignUser->id, 'submitted_at' => now()]);

    $this->actingAs($this->coordinator);
    $this->get(route('waste-management.hauling-plans.index', ['organization_id' => $foreignOrganization->id]))->assertForbidden();
    $this->get(route('waste-management.hauling-plans.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->has('plans', 0)->has('records', 1)->where('records.0.id', $this->record->id));
    $this->get(route('waste-management.hauling-plans.show', ['wasteHaulingPlan' => $foreignPlan->id, 'organization_id' => $foreignOrganization->id]))->assertForbidden();
    $this->get(route('waste-management.hauling-plans.show', $foreignPlan))->assertNotFound();
    $this->post(route('waste-management.hauling-plans.store'), [
        'waste_record_id' => $foreignRecord->id, 'planned_date' => now()->toDateString(), 'quantity' => 10,
    ])->assertSessionHasErrors('waste_record_id');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(WasteHaulingPlan::count())->toBe(0)
        ->and($this->coordinator->canAccessOrganization($foreignOrganization->id))->toBeFalse();
});
