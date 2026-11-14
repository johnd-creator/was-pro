<?php

use App\Http\Middleware\UseOrganizationContext;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use App\Services\OperationalAlerts;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(now()->setDate(2026, 11, 12)->setTime(12, 0));
    config(['hauling-plans.enabled' => false, 'operational-inbox.enabled' => false]);
    $this->organization = Organization::factory()->create();
    $this->tenants = app(TenantService::class);
    $this->tenants->createSchema($this->organization->schema_name);
    $this->tenants->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->role = Role::factory()->create();
    $this->role->permissions()->sync(Permission::where('slug', 'waste_records.view_all')->pluck('id'));
    $this->user = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $this->role->id, 'is_super_admin' => false]);
    $this->actingAs($this->user);
    $this->tenants->switchToSchema($this->organization->schema_name);
    $this->alerts = app(OperationalAlerts::class);
});

afterEach(function () {
    DB::disableQueryLog();
    $this->tenants->switchToPublic();
    $this->travelBack();
});

function performanceAlertRecord(object $test, ?string $expiry = '2026-11-10', array $attributes = []): WasteRecord
{
    $record = WasteRecord::factory()->approved()->create([
        'date' => '2026-10-01', 'quantity' => 100, 'unit' => 'kg', 'created_by' => $test->user->id, ...$attributes,
    ]);
    WasteRecord::withTrashed()->whereKey($record->id)->update(['expiry_date' => $expiry]);

    return $record->refresh();
}

function performanceAlertRequest(object $test, ?User $user = null): Request
{
    $request = Request::create('/dashboard', 'GET', ['month' => '2026-11']);
    $request->attributes->set(UseOrganizationContext::ATTRIBUTE, $test->organization);
    $request->setUserResolver(fn (): User => $user ?? $test->user);

    return $request;
}

test('storage counts match authoritative snapshot balances without loading history', function () {
    $partial = performanceAlertRecord($this);
    WasteHauling::factory()->approved()->create(['waste_record_id' => $partial->id, 'hauling_date' => '2026-11-12', 'quantity' => 40]);
    $full = performanceAlertRecord($this);
    WasteHauling::factory()->approved()->create(['waste_record_id' => $full->id, 'hauling_date' => '2026-11-12', 'quantity' => 100]);
    $future = performanceAlertRecord($this);
    WasteHauling::factory()->approved()->create(['waste_record_id' => $future->id, 'hauling_date' => '2026-11-13', 'quantity' => 100]);
    $pending = performanceAlertRecord($this);
    WasteHauling::factory()->pendingApproval()->create(['waste_record_id' => $pending->id, 'hauling_date' => '2026-11-12', 'quantity' => 100]);
    $deleted = performanceAlertRecord($this);
    WasteHauling::factory()->approved()->create(['waste_record_id' => $deleted->id, 'hauling_date' => '2026-11-12', 'quantity' => 100, 'deleted_at' => now()]);
    performanceAlertRecord($this, '2026-11-12');
    performanceAlertRecord($this, '2026-11-19');
    performanceAlertRecord($this, '2026-11-20');
    performanceAlertRecord($this, null);
    performanceAlertRecord($this, '2026-11-10', ['deleted_at' => now()]);
    performanceAlertRecord($this, '2026-11-10', ['date' => '2026-11-13']);
    performanceAlertRecord($this, '2026-11-10', ['quantity' => 0]);
    $fractional = performanceAlertRecord($this, '2026-11-10', ['quantity' => '0.30']);
    foreach (['0.10', '0.20'] as $quantity) {
        WasteHauling::factory()->approved()->create(['waste_record_id' => $fractional->id, 'hauling_date' => '2026-11-12', 'quantity' => $quantity]);
    }

    $retrieved = 0;
    WasteRecord::retrieved(function () use (&$retrieved): void {
        $retrieved++;
    });
    WasteHauling::retrieved(function () use (&$retrieved): void {
        $retrieved++;
    });
    $result = $this->alerts->summary($this->user, $this->organization, '2026-11');

    expect($result['summary']['expired_waste_count'])->toBe(4)
        ->and($result['summary']['expiring_soon_waste_count'])->toBe(2)
        ->and($retrieved)->toBe(0);
});

test('shared alerts coalesce one request but refresh mutations and permission changes on the next', function () {
    $record = performanceAlertRecord($this);
    $request = performanceAlertRequest($this);
    expect($this->alerts->forRequest($request)['summary']['expired_waste_count'])->toBe(1);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $this->alerts->forRequest($request);
    $this->alerts->forRequest($request);
    expect(DB::getQueryLog())->toBeEmpty();
    DB::disableQueryLog();

    WasteHauling::factory()->approved()->create(['waste_record_id' => $record->id, 'hauling_date' => '2026-11-12', 'quantity' => 100]);
    expect($this->alerts->forRequest(performanceAlertRequest($this))['summary']['expired_waste_count'])->toBe(0);

    performanceAlertRecord($this);
    $this->role->permissions()->detach();
    expect($this->alerts->forRequest(performanceAlertRequest($this))['summary']['total_count'])->toBe(0);
    $this->role->permissions()->sync(Permission::where('slug', 'waste_records.view_own')->pluck('id'));
    $other = User::factory()->create(['organization_id' => $this->organization->id]);
    performanceAlertRecord($this, '2026-11-10', ['created_by' => $other->id]);
    expect($this->alerts->forRequest(performanceAlertRequest($this))['summary']['expired_waste_count'])->toBe(1);
});

test('request memo cannot cross user organization or month contexts', function () {
    performanceAlertRecord($this);
    $request = performanceAlertRequest($this);
    expect($this->alerts->forRequest($request)['summary']['expired_waste_count'])->toBe(1);

    $otherRole = Role::factory()->create();
    $other = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $otherRole->id, 'is_super_admin' => false]);
    $request->setUserResolver(fn (): User => $other);
    expect($this->alerts->forRequest($request)['summary']['total_count'])->toBe(0);

    $request->setUserResolver(fn (): User => $this->user);
    $request->attributes->set('notification_month', '2026-10');
    expect($this->alerts->forRequest($request)['summary']['expired_waste_count'])->toBe(0);
    $request->attributes->set('notification_month', '2026-11');
    $request->attributes->set(UseOrganizationContext::ATTRIBUTE, Organization::factory()->create());
    expect($this->alerts->forRequest($request)['context']['organization_id'])->toBeNull()
        ->and($this->alerts->forRequest($request)['summary']['total_count'])->toBe(0);
});

test('notification summary work stays bounded as historical rows grow', function () {
    $template = performanceAlertRecord($this)->getAttributes();
    $recordRows = [];
    $haulingRows = [];
    for ($index = 1; $index < 1500; $index++) {
        $id = (string) Str::uuid();
        $recordRows[] = [...$template, 'id' => $id, 'record_number' => 'PERF-'.$index];
        $haulingRows[] = [
            'id' => (string) Str::uuid(), 'hauling_number' => 'PERF-H-'.$index,
            'waste_record_id' => $id, 'hauling_date' => '2026-11-01', 'quantity' => '40.00',
            'unit' => 'kg', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ];
    }
    foreach (array_chunk($recordRows, 250) as $rows) {
        WasteRecord::query()->insert($rows);
    }
    foreach (array_chunk($haulingRows, 250) as $rows) {
        WasteHauling::query()->insert($rows);
    }
    DB::statement('ANALYZE waste_records');
    DB::statement('ANALYZE waste_haulings');
    $this->alerts->summary($this->user, $this->organization, '2026-11');
    $retrieved = 0;
    WasteRecord::retrieved(function () use (&$retrieved): void {
        $retrieved++;
    });
    WasteHauling::retrieved(function () use (&$retrieved): void {
        $retrieved++;
    });
    $timings = [];
    DB::enableQueryLog();
    for ($run = 0; $run < 5; $run++) {
        DB::flushQueryLog();
        $started = hrtime(true);
        $result = $this->alerts->summary($this->user, $this->organization, '2026-11');
        $timings[] = (hrtime(true) - $started) / 1e6;
        expect($result['summary']['expired_waste_count'])->toBe(1500);
    }
    sort($timings);
    $queryCount = count(DB::getQueryLog());
    if (getenv('WASPRO_BENCHMARK')) {
        fwrite(STDERR, sprintf("\nALERTS_BENCHMARK median_ms=%.2f queries=%d hydrated=%d rows=1500 haulings=1499\n", $timings[2], $queryCount, $retrieved / 5));
    }
    expect($retrieved)->toBe(0)->and($queryCount)->toBeLessThanOrEqual(10);
});
