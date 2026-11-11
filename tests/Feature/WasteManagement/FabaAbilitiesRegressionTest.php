<?php

use App\Models\FabaMonthlyApproval;
use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\FabaRecapService;
use App\Services\TenantService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->organization = Organization::factory()->create(['schema_name' => 'tenant_abilities_'.Str::lower(Str::random(8))]);
    $this->tenantService = app(TenantService::class);
    $this->tenantService->createSchema($this->organization->schema_name);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    Artisan::call('migrate', ['--path' => 'database/migrations/tenant', '--force' => true]);
    $this->tenantService->switchToPublic();
    $this->operator = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => Role::where('slug', 'operator')->value('id')]);
    $this->reviewer = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => Role::where('slug', 'supervisor')->value('id')]);
    $this->admin = User::factory()->create(['organization_id' => $this->organization->id, 'is_super_admin' => true]);
    $this->tenantService->switchToSchema($this->organization->schema_name);
});

afterEach(function () {
    $this->tenantService->switchToPublic();
});

function abilityMovement(string $type, User $owner, string $status = 'pending_approval'): FabaMovement
{
    test()->tenantService->switchToSchema(test()->organization->schema_name);

    return FabaMovement::factory()->create([
        'transaction_date' => '2026-10-05', 'period_year' => 2026, 'period_month' => 10,
        'movement_type' => $type, 'material_type' => 'fly_ash', 'stock_effect' => 'in',
        'quantity' => 10, 'approval_status' => $status, 'created_by' => $owner->id,
        'submitted_by' => $owner->id, 'vendor_id' => null, 'internal_destination_id' => null, 'purpose_id' => null,
    ]);
}

dataset('faba ability routes', [
    ['production', 'production'], ['utilization', 'utilization_internal'], ['adjustments', 'adjustment_in'],
]);

test('true super admin can edit and delete another users eligible movement', function (string $section, string $type) {
    foreach (['pending_approval', 'rejected'] as $status) {
        $entry = abilityMovement($type, $this->operator, $status);
        $this->actingAs($this->admin)->get(abilityRoute("waste-management.faba.$section.edit", $entry->id))->assertOk();
        $this->delete(abilityRoute("waste-management.faba.$section.destroy", $entry->id))->assertRedirect()->assertSessionHas('success');
        $this->tenantService->switchToSchema($this->organization->schema_name);
        expect(FabaMovement::find($entry->id))->toBeNull();
    }
})->with('faba ability routes');

test('operator ownership and approved or locked movement restrictions remain', function (string $section, string $type) {
    $own = abilityMovement($type, $this->operator);
    $other = abilityMovement($type, $this->reviewer);
    $approved = abilityMovement($type, $this->reviewer, 'approved');
    $this->actingAs($this->operator)->get(abilityRoute("waste-management.faba.$section.edit", $own->id))->assertOk();
    $this->get(abilityRoute("waste-management.faba.$section.edit", $other->id))->assertForbidden();
    $this->delete(abilityRoute("waste-management.faba.$section.destroy", $other->id))->assertForbidden();
    $this->actingAs($this->admin)->get(abilityRoute("waste-management.faba.$section.edit", $approved->id))->assertForbidden();
    $this->delete(abilityRoute("waste-management.faba.$section.destroy", $approved->id))->assertForbidden();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    FabaMonthlyApproval::query()->create(['year' => 2026, 'month' => 10, 'status' => 'submitted', 'submitted_by' => $this->operator->id]);
    $this->get(abilityRoute("waste-management.faba.$section.edit", $other->id))->assertForbidden();
    $this->delete(abilityRoute("waste-management.faba.$section.destroy", $other->id))->assertForbidden();
})->with('faba ability routes');

test('daily approval affordances enforce separation even for super admins but retain rejection', function (string $section, string $type) {
    foreach ([$this->reviewer, $this->admin] as $owner) {
        $entry = abilityMovement($type, $owner);
        $this->actingAs($owner)->get(abilityRoute("waste-management.faba.$section.show", $entry->id))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('entry.can_approve', false)->where('entry.can_reject', true));
        $this->post(abilityRoute('waste-management.faba.movements.approve', $entry->id))->assertRedirect()->assertSessionHas('error');
        $this->tenantService->switchToSchema($this->organization->schema_name);
        expect($entry->fresh()->approval_status)->toBe('pending_approval');
        $independent = $owner->isSuperAdmin() ? $this->reviewer : $this->admin;
        $this->actingAs($independent)->get(abilityRoute("waste-management.faba.$section.show", $entry->id))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('entry.can_approve', true));
    }
})->with('faba ability routes');

test('monthly abilities include permission separation and period state', function () {
    $service = app(FabaRecapService::class);
    $this->actingAs($this->admin);
    expect($service->getPeriodMeta(2026, 10)['can_submit'])->toBeFalse();
    $entry = abilityMovement('production', $this->operator);
    expect($service->getPeriodMeta(2026, 10)['can_submit'])->toBeFalse();
    $entry->update(['approval_status' => 'approved']);
    expect($service->getPeriodMeta(2026, 10)['can_submit'])->toBeTrue();
    $approval = FabaMonthlyApproval::query()->create(['year' => 2026, 'month' => 10, 'status' => 'submitted', 'submitted_by' => $this->admin->id]);
    $meta = $service->getPeriodMeta(2026, 10);
    expect($meta['can_approve'])->toBeFalse()->and($meta['can_reject'])->toBeTrue()->and($meta['can_submit'])->toBeFalse()->and($meta['can_reopen'])->toBeFalse();
    $this->post(abilityRoute('waste-management.faba.approvals.approve', [2026, 10]))->assertRedirect()->assertSessionHas('error');
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $this->actingAs($this->reviewer);
    expect($service->getPeriodMeta(2026, 10)['can_approve'])->toBeTrue();
    $approval->update(['status' => 'approved']);
    $this->actingAs($this->admin);
    $meta = $service->getPeriodMeta(2026, 10);
    expect($meta['can_reopen'])->toBeTrue()->and($meta['can_approve'])->toBeFalse()->and($meta['can_submit'])->toBeFalse();
    $this->actingAs($this->operator);
    expect($service->getPeriodMeta(2026, 10)['can_reopen'])->toBe($this->operator->hasPermission('faba_approvals.reopen'));
});

function abilityRoute(string $name, mixed $parameters = []): string
{
    return route($name, [...(array) $parameters, 'organization_id' => test()->organization->id]);
}
test('movement access remains scoped to active organization and explicit permissions', function (string $section, string $type) {
    $entry = abilityMovement($type, $this->operator);
    $otherOrganization = Organization::factory()->create(['schema_name' => 'tenant_other_'.Str::lower(Str::random(8))]);
    $this->tenantService->createSchema($otherOrganization->schema_name);
    $this->tenantService->switchToSchema($otherOrganization->schema_name);
    Artisan::call('migrate', ['--path' => 'database/migrations/tenant', '--force' => true]);
    $this->actingAs($this->admin)->get(route("waste-management.faba.$section.edit", [$entry->id, 'organization_id' => $otherOrganization->id]))->assertNotFound();
    $this->delete(route("waste-management.faba.$section.destroy", [$entry->id, 'organization_id' => $otherOrganization->id]))->assertNotFound();
    $this->actingAs($this->operator)->get(route("waste-management.faba.$section.edit", [$entry->id, 'organization_id' => $otherOrganization->id]))->assertForbidden();
    $noPermission = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => null]);
    $this->actingAs($noPermission)->get(abilityRoute("waste-management.faba.$section.edit", $entry->id))->assertForbidden();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(FabaMovement::find($entry->id))->not->toBeNull();
    $meta = app(FabaRecapService::class)->getPeriodMeta(2026, 10);
    foreach (['can_submit', 'can_approve', 'can_reject', 'can_review', 'can_reopen'] as $ability) {
        expect($meta[$ability])->toBeFalse();
    }
})->with('faba ability routes');
