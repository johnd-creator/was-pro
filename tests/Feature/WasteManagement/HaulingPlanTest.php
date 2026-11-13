<?php

use App\Exceptions\OperationalConflict;
use App\Models\OperationalAuditLog;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteHaulingPlan;
use App\Models\WasteRecord;
use App\Services\HaulingPlans;
use App\Services\TenantService;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    config(['hauling-plans.enabled' => true]);
    $this->organization = Organization::factory()->create();
    $this->tenants = app(TenantService::class);
    $this->tenants->createSchema($this->organization->schema_name);
    $this->tenants->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenants->switchToPublic();
    $role = Role::query()->where('slug', 'super_admin')->value('id');
    $this->creator = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $role, 'is_super_admin' => true, 'email_verified_at' => now()]);
    $this->supervisor = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $role, 'is_super_admin' => true, 'email_verified_at' => now()]);
    $this->actingAs($this->creator);
    $this->tenants->switchToSchema($this->organization->schema_name);
    $this->record = WasteRecord::factory()->approved()->create(['quantity' => 100, 'unit' => 'kg', 'created_by' => $this->creator->id]);
});

afterEach(function () {
    $this->tenants->switchToPublic();
});

function haulingPlan($test, string $quantity = '80.00'): WasteHaulingPlan
{
    return WasteHaulingPlan::create(['waste_record_id' => $test->record->id, 'planned_date' => now()->addDays(2)->toDateString(), 'quantity' => $quantity, 'unit' => 'kg', 'status' => 'pending_approval', 'created_by' => $test->creator->id, 'submitted_by' => $test->creator->id, 'submitted_at' => now()]);
}

function haulingActual($test, ?WasteHaulingPlan $plan, string $quantity = '30.00'): WasteHauling
{
    return WasteHauling::create(['waste_record_id' => $test->record->id, ...($plan ? ['waste_hauling_plan_id' => $plan->id] : []), 'hauling_date' => now()->toDateString(), 'quantity' => $quantity, 'unit' => 'kg', 'status' => 'pending_approval', 'created_by' => $test->creator->id, 'submitted_by' => $test->creator->id, 'submitted_at' => now()]);
}

function planRoute($test, string $action, ?WasteHaulingPlan $plan = null): string
{
    return route('waste-management.hauling-plans.'.$action, [...($plan ? ['wasteHaulingPlan' => $plan->id] : []), 'organization_id' => $test->organization->id]);
}

test('approved plans reserve capacity without posting outgoing stock and partial actuals do not double count', function () {
    $plan = haulingPlan($this);
    expect($this->record->getRemainingQuantity())->toBe(100.0)->and($this->record->getReservedHaulingQuantity())->toBe(80.0);
    $this->actingAs($this->supervisor);
    expect($plan->approve($this->supervisor->id))->toBeTrue();
    expect($this->record->getRemainingQuantity())->toBe(100.0);
    $this->actingAs($this->creator);
    $actual = haulingActual($this, $plan);
    expect($this->record->getReservedHaulingQuantity())->toBe(80.0)->and($this->record->getRemainingQuantity())->toBe(100.0);
    $this->actingAs($this->supervisor);
    expect($actual->approve($this->supervisor->id))->toBeTrue();
    expect($this->record->getRemainingQuantity())->toBe(70.0)->and($this->record->getReservedHaulingQuantity())->toBe(80.0)
        ->and(app(HaulingPlans::class)->availableFor($this->record))->toBe(20.0);
    expect(OperationalAuditLog::where('module', 'waste_hauling_plans')->count())->toBe(2)
        ->and(OperationalAuditLog::where('module', 'waste_haulings')->latest()->first()->details['after']['waste_hauling_plan_id'])->toBe($plan->id);
});

test('closure releases only unused remainder and is blocked while actual approvals are pending', function () {
    $legacy = haulingActual($this, null, '20.00');
    $this->actingAs($this->supervisor);
    $legacy->approve($this->supervisor->id);
    $this->actingAs($this->creator);
    haulingActual($this, null, '10.00');
    $plan = haulingPlan($this, '50.00');
    $this->actingAs($this->supervisor);
    $plan->approve($this->supervisor->id);
    $this->actingAs($this->creator);
    $actual = haulingActual($this, $plan);
    $this->actingAs($this->supervisor);
    expect($this->record->getReservedHaulingQuantity())->toBe(80.0);
    expect(fn () => $plan->close($this->supervisor->id, 'Sisa tidak diperlukan'))->toThrow(OperationalConflict::class);
    $actual->approve($this->supervisor->id);
    $plan->refresh();
    expect($plan->close($this->supervisor->id, 'Sisa tidak diperlukan'))->toBeTrue();
    expect($this->record->getRemainingQuantity())->toBe(50.0)->and($this->record->getReservedHaulingQuantity())->toBe(60.0)
        ->and(app(HaulingPlans::class)->availableFor($this->record))->toBe(40.0);
});

test('feature toggle does not release commitments and old schema is compatible', function () {
    config(['hauling-plans.enabled' => false]);
    $this->get(planRoute($this, 'index'))->assertNotFound();
    $this->tenants->switchToSchema($this->organization->schema_name);
    $migration = require database_path('migrations/tenant/2026_11_11_100000_create_waste_hauling_plans.php');
    $migration->down();
    expect(app(HaulingPlans::class)->installed())->toBeFalse();
    $legacy = haulingActual($this, null);
    expect($this->record->getReservedHaulingQuantity())->toBe(30.0);
    $migration->up();
    expect($legacy->fresh()->waste_hauling_plan_id)->toBeNull();
    $plan = haulingPlan($this, '50.00');
    expect(app(HaulingPlans::class)->enabled())->toBeFalse()->and($this->record->getReservedHaulingQuantity())->toBe(80.0);
    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
});

test('model guards prevent oversubscription editing deletion and record changes', function () {
    $plan = haulingPlan($this);
    expect(fn () => haulingPlan($this, '21.00'))->toThrow(OperationalConflict::class);
    expect(fn () => haulingActual($this, null, '21.00'))->toThrow(OperationalConflict::class);
    expect(fn () => $plan->update(['quantity' => '90.00']))->toThrow(OperationalConflict::class);
    $plan->refresh();
    expect(fn () => $plan->delete())->toThrow(OperationalConflict::class);
    expect(fn () => $plan->forceFill(['deleted_at' => now()])->save())->toThrow(OperationalConflict::class);
    $plan->refresh();
    expect(fn () => $this->record->update(['quantity' => '90.00']))->toThrow(OperationalConflict::class);
    $this->record->refresh();
    expect(fn () => $this->record->delete())->toThrow(OperationalConflict::class);
});

test('linked actual budgets approval separation and irreversible posting are enforced', function () {
    $plan = haulingPlan($this);
    expect(fn () => $plan->approve($this->creator->id))->toThrow(OperationalConflict::class);
    $plan->refresh();
    $this->actingAs($this->supervisor);
    $plan->approve($this->supervisor->id);
    $this->actingAs($this->creator);
    $actual = haulingActual($this, $plan, '60.00');
    expect(fn () => haulingActual($this, $plan, '21.00'))->toThrow(OperationalConflict::class);
    expect(fn () => $actual->approve($this->creator->id))->toThrow(OperationalConflict::class);
    $actual->refresh();
    expect(fn () => $actual->reject($this->creator->id, 'Tarik'))->toThrow(OperationalConflict::class);
    $actual->refresh();
    expect(fn () => $actual->update(['waste_hauling_plan_id' => null]))->toThrow(OperationalConflict::class);
    $actual->refresh();
    $this->actingAs($this->supervisor);
    $actual->approve($this->supervisor->id);
    expect(fn () => $actual->delete())->toThrow(OperationalConflict::class);
    expect(fn () => $actual->update(['status' => 'cancelled']))->toThrow(OperationalConflict::class);
});

test('http plan submission rejects invalid precision and self approval then supervisor posts plan only', function () {
    $payload = ['waste_record_id' => $this->record->id, 'planned_date' => now()->addWeek()->toDateString(), 'quantity' => '40.00'];
    $this->post(planRoute($this, 'store'), [...$payload, 'quantity' => '0.001'])->assertSessionHasErrors('quantity');
    $this->post(planRoute($this, 'store'), $payload)->assertRedirect()->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    $plan = WasteHaulingPlan::firstOrFail();
    $this->post(planRoute($this, 'approve', $plan))->assertSessionHas('error');
    $this->actingAs($this->supervisor)->post(planRoute($this, 'approve', $plan))->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect($plan->fresh()->status)->toBe('approved')->and($this->record->getRemainingQuantity())->toBe(100.0);
    $this->get(planRoute($this, 'show', $plan))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('waste-management/hauling-plans/Show')->where('plan.remaining_plan_quantity', 40)->where('plan.approved_actual_quantity', 0));
});

test('http linked realization uses plan budget not unplanned headroom', function () {
    $plan = haulingPlan($this, '100.00');
    $this->actingAs($this->supervisor);
    $plan->approve($this->supervisor->id);
    $this->actingAs($this->creator);
    $url = route('waste-management.haulings.store', ['organization_id' => $this->organization->id]);
    $this->post($url, ['waste_record_id' => $this->record->id, 'waste_hauling_plan_id' => $plan->id, 'hauling_date' => now()->toDateString(), 'quantity' => '30.00'])->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    $actual = WasteHauling::firstOrFail();
    expect($actual->waste_hauling_plan_id)->toBe($plan->id);
    $this->actingAs($this->supervisor)->post(route('waste-management.haulings.approve', ['wasteHauling' => $actual->id, 'organization_id' => $this->organization->id]))->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect($this->record->getRemainingQuantity())->toBe(70.0)->and($this->record->getReservedHaulingQuantity())->toBe(100.0);
});

test('web idempotency replays identical plan and actual submissions and rejects changed payloads', function () {
    $payload = ['waste_record_id' => $this->record->id, 'planned_date' => now()->toDateString(), 'quantity' => '40.00'];
    $headers = ['Idempotency-Key' => 'plan-create-test'];
    $url = planRoute($this, 'store');
    $this->post($url, $payload, $headers)->assertSessionHas('success');
    $this->post($url, $payload, $headers)->assertHeader('Idempotency-Replayed', 'true');
    $this->post($url, [...$payload, 'quantity' => '41.00'], $headers)->assertSessionHas('error');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(WasteHaulingPlan::count())->toBe(1);
    $plan = WasteHaulingPlan::firstOrFail();
    $this->actingAs($this->supervisor);
    $plan->approve($this->supervisor->id);
    $this->actingAs($this->creator);
    $payload = ['waste_record_id' => $this->record->id, 'waste_hauling_plan_id' => $plan->id, 'hauling_date' => now()->toDateString(), 'quantity' => '10.00'];
    $url = route('waste-management.haulings.store', ['organization_id' => $this->organization->id]);
    $headers = ['Idempotency-Key' => 'actual-create-test'];
    $this->post($url, $payload, $headers)->assertSessionHas('success');
    $this->post($url, $payload, $headers)->assertHeader('Idempotency-Replayed', 'true');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(WasteHauling::count())->toBe(1)->and(OperationalAuditLog::where('module', 'waste_haulings')->count())->toBe(1);
});

test('non-superadmin cannot view submit or realize another owners plan', function () {
    $plan = haulingPlan($this);
    $this->actingAs($this->supervisor);
    $plan->approve($this->supervisor->id);
    $this->tenants->switchToPublic();
    $operator = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => Role::where('slug', 'operator')->value('id'), 'is_super_admin' => false, 'email_verified_at' => now()]);
    $this->actingAs($operator);
    $this->get(planRoute($this, 'show', $plan))->assertForbidden();
    $this->post(planRoute($this, 'store'), ['waste_record_id' => $this->record->id, 'planned_date' => now()->toDateString(), 'quantity' => 1])->assertForbidden();
    $this->post(route('waste-management.haulings.store', ['organization_id' => $this->organization->id]), ['waste_record_id' => $this->record->id, 'waste_hauling_plan_id' => $plan->id, 'hauling_date' => now()->toDateString(), 'quantity' => 1])->assertForbidden();
    $this->get(planRoute($this, 'index'))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('plans', 0)->has('records', 0));
    $this->post(planRoute($this, 'approve', $plan))->assertForbidden();
    $this->post(planRoute($this, 'close', $plan), ['closure_reason' => 'Unauthorized'])->assertForbidden();
});

test('selected tenant rejects foreign plan identifiers and numbers actuals for selected organization', function () {
    $foreignPlan = haulingPlan($this);
    $this->tenants->switchToPublic();
    $other = Organization::factory()->create();
    $this->tenants->createSchema($other->schema_name);
    $this->tenants->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
    $this->tenants->switchToSchema($other->schema_name);
    $record = WasteRecord::factory()->approved()->create(['quantity' => 100, 'unit' => 'kg', 'created_by' => $this->creator->id]);
    $url = route('waste-management.haulings.store', ['organization_id' => $other->id]);
    $payload = ['waste_record_id' => $record->id, 'waste_hauling_plan_id' => $foreignPlan->id, 'hauling_date' => now()->toDateString(), 'quantity' => 10];
    $this->post($url, $payload)->assertSessionHasErrors('waste_hauling_plan_id');
    unset($payload['waste_hauling_plan_id']);
    $this->post($url, $payload)->assertSessionHas('success');
    $this->tenants->switchToSchema($other->schema_name);
    expect(WasteHauling::firstOrFail()->hauling_number)->toStartWith('WH-'.$other->code.'-');
    $this->get(route('waste-management.hauling-plans.show', ['wasteHaulingPlan' => $foreignPlan->id, 'organization_id' => $other->id]))->assertNotFound();
});

test('rejecting or cancelling actual releases plan budget but not plan allocation', function (string $action) {
    $plan = haulingPlan($this);
    $this->actingAs($this->supervisor);
    $plan->approve($this->supervisor->id);
    $this->actingAs($this->creator);
    $actual = haulingActual($this, $plan);
    $this->actingAs($this->supervisor);
    $action === 'reject' ? $actual->reject($this->supervisor->id, 'Bukti salah') : $actual->cancel();
    expect($this->record->getReservedHaulingQuantity())->toBe(80.0)
        ->and(app(HaulingPlans::class)->availableFor($this->record, $plan->id))->toBe(80.0)
        ->and($this->record->getRemainingQuantity())->toBe(100.0);
})->with(['reject', 'cancel']);

test('rejected plan releases capacity and displays zero remaining allocation', function () {
    $plan = haulingPlan($this);
    $this->actingAs($this->supervisor);
    $plan->reject($this->supervisor->id, 'Tidak diperlukan');
    expect($this->record->getReservedHaulingQuantity())->toBe(0.0);
    $this->get(planRoute($this, 'show', $plan))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('plan.remaining_plan_quantity', 0));
});

test('new actual date must be within incoming and explicit server reference dates and quantities retain precision', function () {
    $plan = haulingPlan($this);
    $this->actingAs($this->supervisor);
    $plan->approve($this->supervisor->id);
    $this->actingAs($this->creator);
    expect(fn () => haulingActual($this, $plan, '0.001'))->toThrow(OperationalConflict::class);
    $url = route('waste-management.haulings.store', ['organization_id' => $this->organization->id]);
    foreach ([now()->addDay()->toDateString(), $this->record->date->subDay()->toDateString()] as $date) {
        $this->post($url, ['waste_record_id' => $this->record->id, 'waste_hauling_plan_id' => $plan->id, 'hauling_date' => $date, 'quantity' => '1.00'])->assertSessionHas('error');
    }
    $this->get(route('waste-management.haulings.create', ['waste_record' => $this->record->id, 'organization_id' => $this->organization->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('actualDateLimit', today()->toDateString())->where('actualDateTimezone', config('app.timezone')));
});

test('missing plan schema remains unavailable even with feature enabled', function () {
    $migration = require database_path('migrations/tenant/2026_11_11_100000_create_waste_hauling_plans.php');
    $migration->down();
    expect(app(HaulingPlans::class)->enabled())->toBeFalse();
    $this->get(planRoute($this, 'index'))->assertNotFound();
    $this->get(route('waste-management.haulings.index', ['organization_id' => $this->organization->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('plansEnabled', false));
    $this->post(planRoute($this, 'store'), ['waste_record_id' => $this->record->id, 'planned_date' => now()->toDateString(), 'quantity' => 1])->assertForbidden();
});

test('historical balance counts actual approved date not the later planned date', function () {
    $this->record->update(['date' => today()->subDays(10)->toDateString()]);
    $plan = haulingPlan($this);
    $this->actingAs($this->supervisor);
    $plan->approve($this->supervisor->id);
    $this->actingAs($this->creator);
    $actual = haulingActual($this, $plan);
    $this->actingAs($this->supervisor);
    $actual->approve($this->supervisor->id);
    $service = app(\App\Services\WasteBalanceService::class);
    $before = $service->report($this->supervisor, today()->subDays(10)->toDateString(), today()->subDay()->toDateString());
    $current = $service->report($this->supervisor, today()->subDays(10)->toDateString(), today()->toDateString());
    expect($before['totals'][0]['outgoing'])->toBe(0.0)->and($current['totals'][0]['outgoing'])->toBe(30.0);
});

function runPlanWorkers(array $requests): array
{
    $directory = storage_path('framework/testing/race-'.\Illuminate\Support\Str::uuid());
    mkdir($directory, 0777, true);
    $workers = [];
    try {
        foreach ($requests as $index => $request) {
            $input = array_merge($request, ['ready' => $directory.'/ready-'.$index, 'go' => $directory.'/go']);
            file_put_contents($directory.'/input-'.$index.'.json', json_encode($input, JSON_THROW_ON_ERROR));
            $worker = new \Symfony\Component\Process\Process([PHP_BINARY, base_path('tests/Fixtures/operational-worker.php'), $directory.'/input-'.$index.'.json'], base_path(), ['WASTE_HAULING_PLANS_ENABLED' => 'true'], timeout: 35);
            $worker->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        do {
            $ready = count(glob($directory.'/ready-*')) === count($workers);
            if (! $ready && microtime(true) > $deadline) {
                throw new RuntimeException('Workers failed to reach barrier: '.implode('\n', array_map(fn (\Symfony\Component\Process\Process $worker): string => $worker->getErrorOutput().$worker->getOutput(), $workers)));
            }
            usleep(10000);
        } while (! $ready);
        touch($directory.'/go');
        $results = array_map(function (\Symfony\Component\Process\Process $worker): array {
            $worker->wait();
            expect($worker->getExitCode())->toBe(0, $worker->getErrorOutput());
            try {
                return json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new RuntimeException('Invalid worker output: '.$worker->getOutput().' STDERR: '.$worker->getErrorOutput(), previous: $exception);
            }
        }, $workers);
        expect(max(array_column($results, 'started')))->toBeLessThan(min(array_column($results, 'finished')));

        return $results;
    } finally {
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        foreach (glob($directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}

test('parallel plan submissions cannot allocate the same incoming capacity', function () {
    \Illuminate\Support\Facades\DB::unprepared('CREATE FUNCTION plan_delay() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN PERFORM pg_sleep(1); RETURN NEW; END $$; CREATE TRIGGER plan_delay BEFORE INSERT ON waste_hauling_plans FOR EACH ROW EXECUTE FUNCTION plan_delay()');
    $request = ['url' => planRoute($this, 'store'), 'web_actor' => $this->creator->id, 'body' => ['waste_record_id' => $this->record->id, 'planned_date' => now()->toDateString(), 'quantity' => '60.00']];
    $this->tenants->switchToPublic();
    $results = runPlanWorkers([$request, $request]);
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(WasteHaulingPlan::count())->toBe(1)->and($this->record->getReservedHaulingQuantity())->toBe(60.0)
        ->and(count(array_filter(array_column($results, 'error'))))->toBe(1);
});

test('parallel linked actual submissions cannot exceed remaining plan budget', function () {
    $plan = haulingPlan($this, '50.00');
    $this->actingAs($this->supervisor);
    $plan->approve($this->supervisor->id);
    \Illuminate\Support\Facades\DB::unprepared('CREATE FUNCTION actual_delay() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN PERFORM pg_sleep(1); RETURN NEW; END $$; CREATE TRIGGER actual_delay BEFORE INSERT ON waste_haulings FOR EACH ROW EXECUTE FUNCTION actual_delay()');
    $request = ['url' => route('waste-management.haulings.store', ['organization_id' => $this->organization->id]), 'web_actor' => $this->creator->id, 'body' => ['waste_record_id' => $this->record->id, 'waste_hauling_plan_id' => $plan->id, 'hauling_date' => now()->toDateString(), 'quantity' => '30.00']];
    $this->tenants->switchToPublic();
    $results = runPlanWorkers([$request, $request]);
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(WasteHauling::count())->toBe(1)->and($this->record->getReservedHaulingQuantity())->toBe(50.0)
        ->and(count(array_filter(array_column($results, 'error'))))->toBe(1);
});

test('failed audit write rolls back newly allocated plan', function () {
    $this->mock(\App\Services\OperationalAuditService::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('audit unavailable'));
    expect(fn () => haulingPlan($this))->toThrow(RuntimeException::class, 'audit unavailable');
    expect(WasteHaulingPlan::count())->toBe(0)->and($this->record->getReservedHaulingQuantity())->toBe(0.0);
});

test('restoring a pending plan rechecks current allocation capacity', function () {
    $plan = haulingPlan($this, '80.00');
    \Illuminate\Support\Facades\DB::table('waste_hauling_plans')->where('id', $plan->id)->update(['deleted_at' => now()]);
    haulingPlan($this, '40.00');
    $deleted = WasteHaulingPlan::withTrashed()->findOrFail($plan->id);
    expect(fn () => $deleted->restore())->toThrow(OperationalConflict::class);
    expect($deleted->fresh()->deleted_at)->not->toBeNull();
});

test('linked date limit explicitly uses server day at a browser day boundary', function () {
    $this->travelTo(\Carbon\Carbon::parse('2026-11-11 23:30:00', 'UTC'));
    try {
        $plan = haulingPlan($this);
        $this->actingAs($this->supervisor);
        $plan->approve($this->supervisor->id);
        $this->actingAs($this->creator);
        $this->get(route('waste-management.haulings.create', ['waste_record' => $this->record->id, 'organization_id' => $this->organization->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('actualDateLimit', '2026-11-11')->where('actualDateTimezone', 'UTC'));
        $this->post(route('waste-management.haulings.store', ['organization_id' => $this->organization->id]), ['waste_record_id' => $this->record->id, 'waste_hauling_plan_id' => $plan->id, 'hauling_date' => '2026-11-12', 'quantity' => 1])->assertSessionHas('error', 'Tanggal realisasi tidak boleh setelah 2026-11-11 (UTC).');
    } finally {
        $this->travelBack();
    }
});

test('plan submission notifications and audit viewer retain scoped plan history', function () {
    config(['operational-inbox.enabled' => true]);
    $this->post(planRoute($this, 'store'), ['waste_record_id' => $this->record->id, 'planned_date' => now()->toDateString(), 'quantity' => 10])->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    $plan = WasteHaulingPlan::firstOrFail();
    expect(\App\Models\OperationalNotification::where('resource_type', 'waste_hauling_plan')->where('recipient_id', $this->supervisor->id)->count())->toBe(1);
    $this->actingAs($this->supervisor)->get(route('waste-management.notifications.index', ['organization_id' => $this->organization->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('notifications.0.source_url', planRoute($this, 'show', $plan)));
    $this->get(route('waste-management.audit.index', ['organization_id' => $this->organization->id, 'module' => 'waste_hauling_plans']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('entries', 1)->where('entries.0.after.waste_record_id', $this->record->id));
    config(['hauling-plans.enabled' => false]);
    $this->get(route('waste-management.notifications.index', ['organization_id' => $this->organization->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('notifications', 0));
});

test('api submits and approves linked actual without double reservation', function () {
    $plan = haulingPlan($this, '100.00');
    $this->actingAs($this->supervisor);
    $plan->approve($this->supervisor->id);
    $this->actingAs($this->creator);
    $token = \Illuminate\Support\Str::random(40);
    \App\Models\ApiToken::create(['user_id' => $this->creator->id, 'name' => 'Plan actual test', 'token' => hash('sha256', $token)]);
    $this->withToken($token);
    $url = route('api.v1.waste-haulings.store', ['organization_id' => $this->organization->id]);
    $payload = ['waste_record_id' => $this->record->id, 'waste_hauling_plan_id' => $plan->id, 'hauling_date' => now()->toDateString(), 'quantity' => '25.00'];
    $this->postJson($url, $payload, ['Idempotency-Key' => 'api-plan-actual'])->assertCreated()->assertJsonPath('data.waste_hauling_plan_id', $plan->id);
    $this->postJson($url, $payload, ['Idempotency-Key' => 'api-plan-actual'])->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(WasteHauling::count())->toBe(1)->and($this->record->getReservedHaulingQuantity())->toBe(100.0);
    $actual = WasteHauling::firstOrFail();
    $token = \Illuminate\Support\Str::random(40);
    \App\Models\ApiToken::create(['user_id' => $this->supervisor->id, 'name' => 'Plan actual reviewer', 'token' => hash('sha256', $token)]);
    $this->actingAs($this->supervisor)->withToken($token);
    $this->postJson(route('api.v1.waste-haulings.approve', ['wasteHauling' => $actual->id, 'organization_id' => $this->organization->id]))->assertOk();
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect($this->record->getRemainingQuantity())->toBe(75.0);
});

test('plan rollback takes the normal writer transaction lock before checking emptiness and holds it through both ddl changes', function () {
    $queries = [];
    \Illuminate\Support\Facades\DB::listen(function (\Illuminate\Database\Events\QueryExecuted $event) use (&$queries): void {
        $queries[] = ['sql' => strtolower($event->sql), 'level' => $event->connection->transactionLevel()];
    });
    $migration = require database_path('migrations/tenant/2026_11_11_100000_create_waste_hauling_plans.php');
    $migration->down();
    $lock = collect($queries)->search(fn ($query) => str_contains($query['sql'], 'pg_advisory_xact_lock') && str_contains($query['sql'], ':operations'));
    $empty = collect($queries)->search(fn ($query) => str_contains($query['sql'], 'select exists') && str_contains($query['sql'], 'waste_hauling_plans'));
    $alter = collect($queries)->search(fn ($query) => str_contains($query['sql'], 'alter table') && str_contains($query['sql'], 'waste_haulings'));
    $drop = collect($queries)->search(fn ($query) => str_contains($query['sql'], 'drop table') && str_contains($query['sql'], 'waste_hauling_plans'));
    expect($lock)->not->toBeFalse()->and($empty)->not->toBeFalse()->and($alter)->not->toBeFalse()->and($drop)->not->toBeFalse();
    expect($lock)->toBeLessThan($empty)->and($empty)->toBeLessThan($alter)->and($alter)->toBeLessThan($drop);
    foreach ([$lock, $empty, $alter, $drop] as $index) {
        expect($queries[$index]['level'])->toBeGreaterThan(0);
    }
    expect(\Illuminate\Support\Facades\DB::transactionLevel())->toBe(0)->and(app(HaulingPlans::class)->installed())->toBeFalse();
});

test('scoped Jakarta day changes at WIB midnight without changing global or legacy dates', function (string $utc, string $day) {
    config(['hauling-plans.timezone' => 'Asia/Jakarta']);
    $this->record->update(['date' => '2026-11-01']);
    $this->travelTo(\Carbon\Carbon::parse($utc, 'UTC'));
    try {
        expect(config('app.timezone'))->toBe('UTC')->and(app(HaulingPlans::class)->actualDateLimit())->toBe($day);
        $plan = haulingPlan($this);
        $this->actingAs($this->supervisor);
        $plan->approve($this->supervisor->id);
        $this->actingAs($this->creator);
        $this->get(route('waste-management.haulings.create', ['waste_record' => $this->record->id, 'organization_id' => $this->organization->id]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('actualDateLimit', $day)->where('actualDateTimezone', 'Asia/Jakarta'));
        $url = route('waste-management.haulings.store', ['organization_id' => $this->organization->id]);
        $payload = ['waste_record_id' => $this->record->id, 'waste_hauling_plan_id' => $plan->id, 'hauling_date' => $day, 'quantity' => '1.00'];
        $this->post($url, $payload)->assertSessionHas('success');
        $tomorrow = \Carbon\Carbon::parse($day)->addDay()->toDateString();
        $this->post($url, [...$payload, 'hauling_date' => $tomorrow])->assertSessionHas('error', 'Tanggal realisasi tidak boleh setelah '.$day.' (Asia/Jakarta).');
        $this->tenants->switchToSchema($this->organization->schema_name);
        $token = \Illuminate\Support\Str::random(40);
        \App\Models\ApiToken::create(['user_id' => $this->creator->id, 'name' => 'Timezone test', 'token' => hash('sha256', $token)]);
        $this->withToken($token)->postJson(route('api.v1.waste-haulings.store', ['organization_id' => $this->organization->id]), $payload)->assertCreated()->assertJsonPath('data.actual_date_limit', $day)->assertJsonPath('data.actual_date_timezone', 'Asia/Jakarta');
        $this->tenants->switchToSchema($this->organization->schema_name);
        $legacy = WasteHauling::create(['waste_record_id' => $this->record->id, 'hauling_date' => $tomorrow, 'quantity' => 1, 'unit' => 'kg', 'status' => 'pending_approval', 'created_by' => $this->creator->id]);
        expect($legacy->waste_hauling_plan_id)->toBeNull()->and($legacy->hauling_date->toDateString())->toBe($tomorrow);
        config(['hauling-plans.timezone' => null]);
        expect(app(HaulingPlans::class)->timezone())->toBe('UTC')->and(app(HaulingPlans::class)->actualDateLimit())->toBe(substr($utc, 0, 10));
    } finally {
        $this->travelBack();
    }
})->with([
    ['2026-11-11 16:59:59', '2026-11-11'],
    ['2026-11-11 17:00:00', '2026-11-12'],
    ['2026-11-11 23:59:59', '2026-11-12'],
    ['2026-11-12 00:00:00', '2026-11-12'],
]);

test('enabled lifecycle records incoming plan partial actual closure and scoped inbox audit over HTTP', function () {
    config(['hauling-plans.timezone' => 'Asia/Jakarta', 'operational-inbox.enabled' => true]);
    $params = ['organization_id' => $this->organization->id];
    $this->post(route('waste-management.records.store', $params), ['date' => today()->toDateString(), 'waste_type_id' => $this->record->waste_type_id, 'quantity' => 100, 'unit' => 'kg'])->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    $record = WasteRecord::where('id', '!=', $this->record->id)->firstOrFail();
    $this->post(route('waste-management.records.submit', [$record->id, ...$params]))->assertSessionHas('success');
    $this->actingAs($this->supervisor)->post(route('waste-management.records.approve', [$record->id, ...$params]))->assertSessionHas('success');
    $this->actingAs($this->creator)->post(planRoute($this, 'store'), ['waste_record_id' => $record->id, 'planned_date' => today()->addDays(2)->toDateString(), 'quantity' => 80])->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    $plan = WasteHaulingPlan::firstOrFail();
    $this->actingAs($this->supervisor)->post(planRoute($this, 'approve', $plan))->assertSessionHas('success');
    $this->actingAs($this->creator)->post(route('waste-management.haulings.store', $params), ['waste_record_id' => $record->id, 'waste_hauling_plan_id' => $plan->id, 'hauling_date' => app(HaulingPlans::class)->actualDateLimit(), 'quantity' => 30])->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    $actual = WasteHauling::firstOrFail();
    expect($actual->document_number)->toBeNull()->and($actual->attachment_path)->toBeNull();
    $this->actingAs($this->supervisor)->post(planRoute($this, 'close', $plan), ['closure_reason' => 'Sisa tidak diperlukan'])->assertSessionHas('error');
    $this->post(route('waste-management.haulings.approve', [$actual->id, ...$params]))->assertSessionHas('success');
    $this->post(planRoute($this, 'close', $plan), [])->assertSessionHasErrors('closure_reason');
    $this->post(planRoute($this, 'close', $plan), ['closure_reason' => 'Sisa tidak diperlukan'])->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect($plan->fresh()->status)->toBe('closed')->and($record->getRemainingQuantity())->toBe(70.0)->and(app(HaulingPlans::class)->availableFor($record))->toBe(70.0);
    expect(\App\Models\OperationalNotification::count())->toBe(6);
    expect(OperationalAuditLog::where('module', 'waste_hauling_plans')->count())->toBe(3);
    $this->actingAs($this->creator)->get(route('waste-management.notifications.index', $params))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('available', true)->has('notifications', 3));
    $this->actingAs($this->supervisor)->get(route('waste-management.audit.index', [...$params, 'module' => 'waste_hauling_plans']))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('entries', 3));
});

test('http plan over-allocation explains the batch limit and saves no plan or audit', function () {
    haulingPlan($this, '60.00');
    $auditCount = OperationalAuditLog::where('module', 'waste_hauling_plans')->count();
    $payload = ['waste_record_id' => $this->record->id, 'planned_date' => now()->addWeek()->toDateString(), 'quantity' => '40.01'];
    $this->post(planRoute($this, 'store'), $payload)->assertSessionHas('error', fn ($message) => str_contains($message, '40,00 kg') && str_contains($message, $this->record->record_number));
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(WasteHaulingPlan::count())->toBe(1)
        ->and(OperationalAuditLog::where('module', 'waste_hauling_plans')->count())->toBe($auditCount)
        ->and($this->record->getRemainingQuantity())->toBe(100.0);
    $this->post(planRoute($this, 'store'), [...$payload, 'quantity' => '40.00'])->assertSessionHas('success');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(WasteHaulingPlan::count())->toBe(2)
        ->and(app(HaulingPlans::class)->availableFor($this->record))->toBe(0.0)
        ->and($this->record->getRemainingQuantity())->toBe(100.0);
});

test('invalid plan quantities return field warnings without creating operational rows', function (mixed $quantity) {
    $this->post(planRoute($this, 'store'), ['waste_record_id' => $this->record->id, 'planned_date' => now()->addWeek()->toDateString(), 'quantity' => $quantity])->assertSessionHasErrors('quantity');
    $this->tenants->switchToSchema($this->organization->schema_name);
    expect(WasteHaulingPlan::count())->toBe(0)->and(WasteHauling::count())->toBe(0);
})->with(['empty' => '', 'zero' => '0', 'negative' => '-1', 'precision' => '1.001', 'non-numeric' => 'asal', 'too-large' => '10000000000']);
