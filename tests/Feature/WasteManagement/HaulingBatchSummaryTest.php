<?php

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteHaulingPlan;
use App\Models\WasteRecord;
use App\Services\HaulingPlans;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->withoutVite();
    $this->organization = Organization::factory()->create();
    $this->tenants = app(TenantService::class);
    $this->tenants->createSchema($this->organization->schema_name);
    $this->tenants->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenants->switchToPublic();
    $this->user = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => Role::where('slug', 'super_admin')->value('id'), 'is_super_admin' => true]);
    $this->actingAs($this->user);
    $this->tenants->switchToSchema($this->organization->schema_name);
    $this->record = WasteRecord::factory()->approved()->create(['quantity' => 100, 'unit' => 'kg', 'created_by' => $this->user->id]);
});

afterEach(function () {
    DB::disconnect('summary_peer');
    $this->tenants->switchToPublic();
});

/** Seed historical read fixtures directly; do not use these bypasses for application commands. */
function summaryPlan(WasteRecord $record, string $status, string $quantity, bool $deleted = false): string
{
    $id = (string) Str::uuid();
    WasteHaulingPlan::query()->insert(['id' => $id, 'waste_record_id' => $record->id, 'planned_date' => today()->addMonth()->toDateString(), 'quantity' => $quantity, 'unit' => $record->unit, 'status' => $status, 'created_by' => auth()->id(), 'submitted_by' => auth()->id(), 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $deleted ? now() : null]);

    return $id;
}

/** Seed historical read fixtures, including states no longer accepted by current write guards. */
function summaryActual(WasteRecord $record, string $status, string $quantity, ?string $plan = null, bool $deleted = false, bool $future = false): string
{
    $id = (string) Str::uuid();
    WasteHauling::query()->insert(['id' => $id, 'hauling_number' => 'BATCH-'.$id, 'waste_record_id' => $record->id, 'waste_hauling_plan_id' => $plan, 'hauling_date' => $future ? today()->addMonth()->toDateString() : today()->toDateString(), 'quantity' => $quantity, 'unit' => $record->unit, 'status' => $status, 'created_by' => auth()->id(), 'submitted_by' => auth()->id(), 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => $deleted ? now() : null]);

    return $id;
}

function expectEquivalentAllocation(HaulingPlans $service, WasteRecord $record, array $batch): void
{
    expect($batch[$record->id])->toEqual(['reserved_quantity' => $service->reserved($record) / 100, ...$service->summary($record)]);
}

test('batch hauling summaries equal scalar semantics for partial plans future actuals decimals and soft deletes', function () {
    $plan = summaryPlan($this->record, 'approved', '60.00');
    summaryActual($this->record, 'approved', '20.00', $plan);
    summaryActual($this->record, 'pending_approval', '10.00', $plan);
    summaryActual($this->record, 'approved', '5.55');
    summaryActual($this->record, 'pending_approval', '2.22');
    summaryActual($this->record, 'approved', '1.11', future: true);
    summaryPlan($this->record, 'pending_approval', '8.88');
    $closed = summaryPlan($this->record, 'closed', '40.00');
    summaryActual($this->record, 'approved', '2.00', $closed);
    summaryPlan($this->record, 'rejected', '40.00');
    $deleted = summaryPlan($this->record, 'approved', '40.00', deleted: true);
    summaryActual($this->record, 'approved', '1.00', $deleted);
    summaryActual($this->record, 'approved', '40.00', deleted: true);
    summaryActual($this->record, 'pending_approval', '40.00', $plan, deleted: true);
    foreach (['draft', 'rejected', 'cancelled'] as $status) {
        summaryActual($this->record, $status, '40.00', $plan);
    }
    $empty = WasteRecord::factory()->approved()->create(['quantity' => 0.01, 'unit' => 'liter']);
    $service = app(HaulingPlans::class);
    $batch = $service->summaries(collect([$this->record, $empty]));
    expectEquivalentAllocation($service, $this->record, $batch);
    expectEquivalentAllocation($service, $empty, $batch);
    expect($batch[$this->record->id])->toEqual(['reserved_quantity' => 80.76, 'pending_actual_quantity' => 12.22, 'plan_reserved_quantity' => 38.88, 'available_quantity' => 19.24]);
    expect($batch[$empty->id]['available_quantity'])->toEqual(0.01);
    config(['hauling-plans.enabled' => false]);
    expect($service->summaries(collect([$this->record, $empty])))->toEqual($batch);
});

test('batch hauling summaries issue a bounded number of queries and no queries for empty input', function () {
    $service = app(HaulingPlans::class);
    DB::enableQueryLog();
    DB::flushQueryLog();
    expect($service->summaries(collect()))->toBe([]);
    expect(DB::getQueryLog())->toBe([]);
    $service->summaries(collect([$this->record]));
    $smallCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    $many = WasteRecord::factory()->approved()->count(25)->create(['quantity' => 100, 'unit' => 'kg']);
    DB::enableQueryLog();
    DB::flushQueryLog();
    expect($service->summaries($many))->toHaveCount(25);
    expect(count(DB::getQueryLog()))->toBe($smallCount)->toBeLessThanOrEqual(4);
    DB::disableQueryLog();
});

test('batch hauling summaries support legacy tenant schema without hauling plans', function () {
    $migration = require database_path('migrations/tenant/2026_11_11_100000_create_waste_hauling_plans.php');
    $migration->down();
    WasteHauling::factory()->pendingApproval()->create(['waste_record_id' => $this->record->id, 'quantity' => 10.12, 'unit' => 'kg']);
    $service = app(HaulingPlans::class);
    expect($service->installed())->toBeFalse();
    $batch = $service->summaries(collect([$this->record]));
    expectEquivalentAllocation($service, $this->record, $batch);
    expect($batch[$this->record->id])->toEqual(['reserved_quantity' => 10.12, 'pending_actual_quantity' => 10.12, 'plan_reserved_quantity' => 0, 'available_quantity' => 89.88]);
});

test('batch allocation reads fresh committed quantities rather than stale eager-loaded relationships', function () {
    $actual = summaryActual($this->record, 'pending_approval', '20.00');
    $this->record->load('haulings');
    $service = app(HaulingPlans::class);
    config(['database.connections.summary_peer' => config('database.connections.pgsql')]);
    $peer = DB::connection('summary_peer');
    $peer->statement('SET search_path TO "'.$this->organization->schema_name.'", public');
    try {
        $peer->beginTransaction();
        $peer->table('waste_haulings')->where('id', $actual)->update(['quantity' => 40]);
        expect($service->summaries(collect([$this->record]))[$this->record->id]['available_quantity'])->toEqual(80);
        $peer->commit();
        $batch = $service->summaries(collect([$this->record]));
        expect($this->record->haulings->first()->quantity)->toBe('20.00');
        expect($batch[$this->record->id]['available_quantity'])->toEqual(60);
        expectEquivalentAllocation($service, $this->record, $batch);
    } finally {
        if ($peer->transactionLevel() > 0) {
            $peer->rollBack();
        }
        DB::purge('summary_peer');
    }
});
