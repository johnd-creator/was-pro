<?php

use App\Exceptions\OperationalConflict;
use App\Models\ApiToken;
use App\Models\FabaMovement;
use App\Models\OperationalNotification;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteRecord;
use App\Services\FabaIntegrityService;
use App\Services\OperationalInbox;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    config(['operational-inbox.enabled' => true]);
    $this->organization = Organization::factory()->create();
    $this->tenants = app(TenantService::class);
    $this->tenants->createSchema($this->organization->schema_name);
    $this->tenants->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenants->switchToPublic();
    $this->role = Role::factory()->create();
    $this->role->permissions()->sync(Permission::pluck('id'));
    $this->owner = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $this->role->id, 'is_super_admin' => false]);
    $this->reviewer = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $this->role->id, 'is_super_admin' => false]);
    $this->actingAs($this->owner);
    $this->tenants->switchToSchema($this->organization->schema_name);
});

afterEach(function () {
    $this->tenants->switchToPublic();
});

function inboxUrl($test, string $name, array $parameters = []): string
{
    return route('waste-management.'.$name, [...$parameters, 'organization_id' => $test->organization->id]);
}

function inboxDraft($test): WasteRecord
{
    $test->tenants->switchToSchema($test->organization->schema_name);

    return WasteRecord::factory()->draft()->create(['created_by' => $test->owner->id, 'quantity' => 100, 'unit' => 'kg']);
}

function inboxSubmit($test): WasteRecord
{
    $record = inboxDraft($test);
    $test->actingAs($test->owner)->post(inboxUrl($test, 'records.submit', [$record->id]))->assertRedirect()->assertSessionHas('success');
    $test->tenants->switchToSchema($test->organization->schema_name);

    return $record->fresh();
}

function inboxToken($test): void
{
    $token = Str::random(40);
    ApiToken::create(['user_id' => $test->owner->id, 'name' => 'inbox', 'token' => hash('sha256', $token)]);
    $test->withToken($token);
}

test('pending recipients require tenant permission and source access and exclude actor', function () {
    $viewerRole = Role::factory()->create();
    $viewerRole->permissions()->sync(Permission::where('slug', 'waste_records.view_all')->pluck('id'));
    User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $viewerRole->id]);
    $approveRole = Role::factory()->create();
    $approveRole->permissions()->sync(Permission::where('slug', 'waste_records.approve')->pluck('id'));
    User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $approveRole->id]);
    User::factory()->create(['organization_id' => Organization::factory()->create()->id, 'role_id' => $this->role->id, 'is_super_admin' => true]);
    $record = inboxSubmit($this);
    expect(OperationalNotification::pluck('recipient_id')->all())->toBe([$this->reviewer->id]);
    expect(OperationalNotification::first()->resource_id)->toBe($record->id);
    $this->actingAs($this->reviewer)->get(inboxUrl($this, 'notifications.index'))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('available', true)->where('unreadCount', 1)->has('notifications', 1)->where('notifications.0.event', 'submitted')
            ->where('notifications.0.reference', $record->record_number)->where('notifications.0.source_date', $record->date->toDateString())
            ->where('notifications.0.source_url', inboxUrl($this, 'records.show', [$record->id]))->missing('notifications.0.attachment_path'));
});

test('read state is recipient scoped reversible and hidden after permission revocation', function () {
    inboxSubmit($this);
    $id = OperationalNotification::first()->id;
    $this->patch(inboxUrl($this, 'notifications.update', [$id]), ['read' => true])->assertNotFound();
    $this->actingAs($this->reviewer)->patch(inboxUrl($this, 'notifications.update', [$id]), ['read' => true])->assertRedirect();
    $this->tenants->switchToSchema($this->organization->schema_name);
    $readAt = OperationalNotification::findOrFail($id)->read_at->toIso8601String();
    $this->patch(inboxUrl($this, 'notifications.update', [$id]), ['read' => true])->assertRedirect();
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(OperationalNotification::findOrFail($id)->read_at->toIso8601String())->toBe($readAt);
    $this->patch(inboxUrl($this, 'notifications.update', [$id]), ['read' => false])->assertRedirect();
    $this->get(inboxUrl($this, 'notifications.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('unreadCount', 1));
    $this->role->permissions()->detach();
    $this->reviewer->unsetRelation('role');
    $this->get(inboxUrl($this, 'notifications.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('unreadCount', 0)->has('notifications', 0));
    $this->patch(inboxUrl($this, 'notifications.update', [$id]), ['read' => true])->assertNotFound();
});

test('decisions notify submitter and historical pending event shows current state', function (string $decision) {
    $record = inboxSubmit($this);
    $pendingId = OperationalNotification::first()->id;
    $this->actingAs($this->reviewer)->post(inboxUrl($this, 'records.'.$decision, [$record->id]), ['rejection_reason' => 'Perlu koreksi'])->assertRedirect()->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    $outcome = OperationalNotification::where('recipient_id', $this->owner->id)->firstOrFail();
    expect($outcome->event)->toBe($decision === 'approve' ? 'approved' : 'rejected');
    $this->get(inboxUrl($this, 'notifications.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('notifications.0.id', $pendingId)->where('notifications.0.current_status', $outcome->event));
    $this->actingAs($this->owner)->get(inboxUrl($this, 'notifications.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('notifications.0.id', $outcome->id));
})->with(['approve', 'reject']);

test('legitimate resubmission creates new event but later CLI saves do not notify', function () {
    $record = inboxSubmit($this);
    $this->actingAs($this->reviewer)->post(inboxUrl($this, 'records.reject', [$record->id]), ['rejection_reason' => 'Perlu koreksi'])->assertRedirect()->assertSessionHas('success');
    $this->actingAs($this->owner)->post(inboxUrl($this, 'records.return-to-draft', [$record->id]))->assertRedirect()->assertSessionHas('success');
    $this->post(inboxUrl($this, 'records.submit', [$record->id]))->assertRedirect()->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(OperationalNotification::where('event', 'submitted')->count())->toBe(2);
    expect(request()->attributes->get(OperationalInbox::CAPTURE))->toBeNull();
    $record->fresh()->update(['notes' => 'CLI maintenance after HTTP']);
    WasteRecord::factory()->pendingReview()->create(['created_by' => $this->owner->id, 'submitted_by' => $this->owner->id]);
    expect(OperationalNotification::count())->toBe(3);
});

test('disabled inbox and old schema never block writes or expose navigation', function (bool $missingTable) {
    if ($missingTable) {
        $migration = require database_path('migrations/tenant/2026_11_11_061041_create_operational_notifications_table.php');
        $migration->down();
    } else {
        config(['operational-inbox.enabled' => false]);
    }
    inboxSubmit($this);
    $this->get(inboxUrl($this, 'notifications.index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('available', false)->where('operational_inbox_available', false)->where('unreadCount', 0)->has('notifications', 0));
    $this->patch(inboxUrl($this, 'notifications.update', [str_repeat('a', 64)]), ['read' => true])->assertNotFound();
})->with([false, true]);

test('faba idempotency replay emits once and same pending update emits nothing', function () {
    inboxToken($this);
    $payload = ['transaction_date' => '2026-01-01', 'material_type' => 'fly_ash', 'movement_type' => 'production', 'quantity' => 10, 'unit' => 'ton'];
    $first = $this->withHeader('Idempotency-Key', 'inbox-create')->postJson('/api/v1/faba/production', $payload)->assertCreated();
    $this->postJson('/api/v1/faba/production', $payload)->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(OperationalNotification::count())->toBe(1)->and(FabaMovement::count())->toBe(1);
    $this->withoutHeader('Idempotency-Key')->putJson('/api/v1/faba/production/'.$first->json('data.id'), [...$payload, 'quantity' => 11])->assertOk();
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(OperationalNotification::count())->toBe(1);
});

test('post-controller integrity failure rolls back inbox and business data', function (bool $api) {
    $this->partialMock(FabaIntegrityService::class, function ($mock) {
        $mock->shouldReceive('validate')->once()->andReturnUsing(function () {
            expect(OperationalNotification::count())->toBe(1);
            throw new OperationalConflict('Test rollback after notification insertion');
        });
    });
    $payload = ['transaction_date' => '2026-01-01', 'material_type' => 'fly_ash', 'movement_type' => 'production', 'quantity' => 10, 'unit' => 'ton'];
    if ($api) {
        inboxToken($this);
        $this->postJson('/api/v1/faba/production', $payload)->assertConflict();
    } else {
        $this->post(inboxUrl($this, 'faba.production.store'), $payload)->assertRedirect()->assertSessionHas('error');
    }
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(OperationalNotification::count())->toBe(0)->and(FabaMovement::count())->toBe(0)
        ->and(request()->attributes->get(OperationalInbox::CAPTURE))->toBeNull();
})->with([false, true]);

test('stable event deduplicates and nested rollback removes notifications', function () {
    $record = inboxDraft($this);
    $before = $record->getAttributes();
    $record->submitForApproval($this->owner->id);
    $eventId = (string) Str::uuid();
    request()->attributes->set(OperationalInbox::CAPTURE, true);
    DB::beginTransaction();
    app(OperationalInbox::class)->recordTransition($record, $before, $eventId);
    app(OperationalInbox::class)->recordTransition($record, $before, $eventId);
    expect(OperationalNotification::count())->toBe(1);
    DB::rollBack();
    request()->attributes->remove(OperationalInbox::CAPTURE);
    expect(OperationalNotification::count())->toBe(0);
});

test('selected tenant not superadmin home determines inbox and other tenant cannot access', function () {
    $home = Organization::factory()->create();
    $this->owner->update(['organization_id' => $home->id, 'is_super_admin' => true]);
    $this->owner->refresh();
    inboxSubmit($this);
    expect(OperationalNotification::first()->organization_id)->toBe($this->organization->id);
    $id = OperationalNotification::first()->id;
    $this->tenants->createSchema($home->schema_name);
    $this->tenants->runMigrationsForTenant($home->schema_name, 'database/migrations/tenant');
    $this->get(route('waste-management.notifications.index', ['organization_id' => $home->id]))->assertInertia(fn (AssertableInertia $page) => $page->where('unreadCount', 0)->has('notifications', 0));
    $this->patch(route('waste-management.notifications.update', ['notification' => $id, 'organization_id' => $home->id]), ['read' => true])->assertNotFound();
    $this->actingAs($this->reviewer)->get(route('waste-management.notifications.index', ['organization_id' => $home->id]))->assertForbidden();
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(OperationalNotification::whereKey($id)->first()->read_at)->toBeNull();
});

test('approval permission revocation hides pending events before counts and pagination', function () {
    inboxSubmit($this);
    $this->role->permissions()->detach(Permission::whereIn('slug', ['waste_records.approve', 'waste_records.reject'])->pluck('id'));
    $this->reviewer->unsetRelation('role');
    $this->actingAs($this->reviewer)->get(inboxUrl($this, 'notifications.index'))->assertInertia(fn (AssertableInertia $page) => $page->where('unreadCount', 0)->has('notifications', 0)->where('hasMore', false));
});

test('hauling and closing use their own existing workflow recipients', function (string $kind) {
    if ($kind === 'hauling') {
        $record = WasteRecord::factory()->approved()->create(['created_by' => $this->owner->id, 'quantity' => 100]);
        $this->post(inboxUrl($this, 'haulings.store'), ['waste_record_id' => $record->id, 'hauling_date' => now()->toDateString(), 'quantity' => 10])->assertRedirect()->assertSessionHas('success');
        $this->tenants->switchToSchema($this->organization->schema_name);
        $hauling = \App\Models\WasteHauling::firstOrFail();
        $this->actingAs($this->reviewer)->post(inboxUrl($this, 'haulings.approve', [$hauling->id]))->assertRedirect()->assertSessionHas('success');
    } else {
        FabaMovement::factory()->create(['transaction_date' => '2026-01-01', 'period_year' => 2026, 'period_month' => 1, 'movement_type' => 'production', 'stock_effect' => 'in', 'quantity' => 10, 'material_type' => 'fly_ash']);
        $this->post(inboxUrl($this, 'faba.approvals.submit'), ['year' => 2026, 'month' => 1])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($this->reviewer)->post(inboxUrl($this, 'faba.approvals.reject', [2026, 1]), ['rejection_note' => 'Perlu koreksi closing'])->assertRedirect()->assertSessionHas('success');
    }
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(OperationalNotification::count())->toBe(2);
    expect(OperationalNotification::where('event', 'submitted')->first()->recipient_id)->toBe($this->reviewer->id);
    expect(OperationalNotification::where('event', '!=', 'submitted')->first()->recipient_id)->toBe($this->owner->id);
})->with(['hauling', 'closing']);

test('faba decision and resubmission notify without duplicating unchanged pending edits', function () {
    $payload = ['transaction_date' => '2026-01-01', 'material_type' => 'fly_ash', 'movement_type' => 'production', 'quantity' => 10, 'unit' => 'ton'];
    $this->post(inboxUrl($this, 'faba.production.store'), $payload)->assertRedirect()->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    $movement = FabaMovement::firstOrFail();
    $this->actingAs($this->reviewer)->post(inboxUrl($this, 'faba.movements.reject', [$movement->id]), ['rejection_note' => 'Perlu koreksi produksi'])->assertRedirect()->assertSessionHas('success');
    $this->actingAs($this->owner)->put(inboxUrl($this, 'faba.production.update', [$movement->id]), $payload)->assertRedirect()->assertSessionHas('success');
    $this->put(inboxUrl($this, 'faba.production.update', [$movement->id]), [...$payload, 'quantity' => 12])->assertRedirect()->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(OperationalNotification::where('event', 'submitted')->count())->toBe(2)
        ->and(OperationalNotification::where('event', 'rejected')->first()->recipient_id)->toBe($this->owner->id);
    $this->actingAs($this->reviewer)->post(inboxUrl($this, 'faba.movements.approve', [$movement->id]))->assertRedirect()->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(OperationalNotification::where('event', 'approved')->first()->recipient_id)->toBe($this->owner->id);
});

test('creator who is not current actor is excluded by separation and sources use allowed tenant routes', function (string $type, string $permission, string $route) {
    $other = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $this->role->id, 'is_super_admin' => false]);
    $movement = FabaMovement::factory()->create([
        'transaction_date' => '2026-01-01', 'period_year' => 2026, 'period_month' => 1,
        'movement_type' => $type, 'stock_effect' => 'in', 'approval_status' => 'pending_approval',
        'created_by' => $this->reviewer->id, 'submitted_by' => $this->owner->id,
        'document_number' => 'SOURCE-2026',
    ]);
    request()->attributes->set(OperationalInbox::CAPTURE, true);
    DB::transaction(fn () => app(OperationalInbox::class)->recordTransition($movement, [], (string) Str::uuid()));
    request()->attributes->remove(OperationalInbox::CAPTURE);
    expect(OperationalNotification::pluck('recipient_id')->all())->toBe([$other->id]);
    $this->actingAs($other)->get(inboxUrl($this, 'notifications.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('notifications.0.reference', 'SOURCE-2026')
        ->where('notifications.0.source_url', inboxUrl($this, $route, [$movement->id])));
    $this->role->permissions()->detach(Permission::where('slug', $permission)->pluck('id'));
    $other->unsetRelation('role');
    $this->get(inboxUrl($this, 'notifications.index'))->assertInertia(fn (AssertableInertia $page) => $page->has('notifications', 0)->where('unreadCount', 0));
})->with([
    ['reject', 'faba_production.view', 'faba.production.show'],
    ['utilization_external', 'faba_utilization.view', 'faba.utilization.show'],
    ['adjustment_in', 'faba_adjustments.view', 'faba.adjustments.show'],
]);
