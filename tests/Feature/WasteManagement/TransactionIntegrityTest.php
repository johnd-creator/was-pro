<?php

use App\Models\ApiToken;
use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

beforeEach(function () {
    $this->organization = Organization::factory()->create(['schema_name' => 'tenant_integrity_'.Str::lower(Str::random(8))]);
    $tenants = app(TenantService::class);
    $tenants->createSchema($this->organization->schema_name);
    $tenants->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $tenants->switchToPublic();
    $this->actor = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::query()->where('slug', 'supervisor')->firstOrFail()->id,
    ]);
    $this->token = Str::random(40);
    ApiToken::query()->create(['user_id' => $this->actor->id, 'name' => 'concurrency-test', 'token' => hash('sha256', $this->token), 'expires_at' => now()->addDay()]);
    $tenants->switchToSchema($this->organization->schema_name);
});

function integrityMovement(array $attributes = []): FabaMovement
{
    return FabaMovement::factory()->create(array_merge([
        'transaction_date' => '2026-01-10', 'period_year' => 2026, 'period_month' => 1,
        'material_type' => 'fly_ash', 'movement_type' => 'production', 'stock_effect' => 'in',
        'quantity' => 100, 'vendor_id' => null, 'internal_destination_id' => null, 'purpose_id' => null,
    ], $attributes));
}

function runIntegrityWorkers(array $requests): array
{
    $directory = storage_path('framework/testing/race-'.Str::uuid());
    mkdir($directory, 0777, true);
    $workers = [];
    try {
        foreach ($requests as $index => $request) {
            $input = array_merge($request, ['ready' => $directory.'/ready-'.$index, 'go' => $directory.'/go']);
            file_put_contents($directory.'/input-'.$index.'.json', json_encode($input, JSON_THROW_ON_ERROR));
            $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/operational-worker.php'), $directory.'/input-'.$index.'.json'], base_path(), timeout: 35);
            $worker->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        do {
            $ready = count(glob($directory.'/ready-*')) === count($workers);
            if (! $ready && microtime(true) > $deadline) {
                throw new RuntimeException('Workers failed to reach barrier: '.implode('\n', array_map(fn (Process $worker): string => $worker->getErrorOutput().$worker->getOutput(), $workers)));
            }
            usleep(10000);
        } while (! $ready);
        touch($directory.'/go');
        $results = array_map(function (Process $worker): array {
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

test('two independent approvals cannot spend the same faba stock', function () {
    integrityMovement();
    $first = integrityMovement(['quantity' => 70, 'movement_type' => 'adjustment_out', 'stock_effect' => 'out', 'approval_status' => 'pending_approval']);
    $second = integrityMovement(['quantity' => 70, 'movement_type' => 'adjustment_out', 'stock_effect' => 'out', 'approval_status' => 'pending_approval']);
    DB::unprepared("CREATE FUNCTION integrity_delay() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.approval_status = 'approved' AND OLD.approval_status <> 'approved' THEN PERFORM pg_sleep(1); END IF; RETURN NEW; END $$; CREATE TRIGGER integrity_delay BEFORE UPDATE ON faba_movements FOR EACH ROW EXECUTE FUNCTION integrity_delay()");
    app(TenantService::class)->switchToPublic();
    $results = runIntegrityWorkers(array_map(fn (FabaMovement $movement): array => [
        'url' => '/api/v1/faba/movements/'.$movement->id.'/approve', 'token' => $this->token,
    ], [$first, $second]));
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    $statuses = array_column($results, 'status');
    sort($statuses);
    expect($statuses)->toBe([200, 409]);
    expect(FabaMovement::query()->approved()->where('stock_effect', 'out')->sum('quantity'))->toBe('70.00');
});

test('explicit opening resets stock including zero and agrees with recap and stock card', function () {
    $recaps = app(\App\Services\FabaRecapService::class);
    $ledger = app(\App\Services\FabaMovementLedgerService::class);
    $recaps->setOpeningBalance(2026, 1, 'fly_ash', 100, null, $this->actor->id);
    integrityMovement(['quantity' => 20, 'stock_effect' => 'out', 'movement_type' => 'adjustment_out']);
    $recaps->setOpeningBalance(2026, 2, 'fly_ash', 80, null, $this->actor->id);
    expect($ledger->calculateAvailableStock('fly_ash', \Carbon\CarbonImmutable::parse('2026-02-28')))->toBe(80.0)
        ->and($recaps->getMonthlyRecap(2026, 2)['closing_fly_ash'])->toBe(80.0)
        ->and($recaps->getStockCard(2026, 2, 'fly_ash')['rows']->last()['running_balance'])->toBe(80.0);
    $recaps->setOpeningBalance(2026, 2, 'fly_ash', 0, null, $this->actor->id);
    expect($ledger->calculateAvailableStock('fly_ash', \Carbon\CarbonImmutable::parse('2026-02-28')))->toBe(0.0)
        ->and($recaps->getMonthlyRecap(2026, 2)['closing_fly_ash'])->toBe(0.0)
        ->and($recaps->getMonthlyRecap(2026, 3)['opening_fly_ash'])->toBe(0.0);
});

test('backdated approval cannot invalidate later approved stock and audit rolls back', function () {
    integrityMovement(['transaction_date' => '2026-01-01']);
    integrityMovement(['transaction_date' => '2026-01-03', 'quantity' => 100, 'movement_type' => 'adjustment_out', 'stock_effect' => 'out']);
    $pending = integrityMovement(['transaction_date' => '2026-01-02', 'quantity' => 20, 'movement_type' => 'adjustment_out', 'stock_effect' => 'out', 'approval_status' => 'pending_approval']);
    app(TenantService::class)->switchToPublic();
    $this->withToken($this->token)->postJson('/api/v1/faba/movements/'.$pending->id.'/approve')->assertConflict();
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    expect($pending->fresh()->approval_status)->toBe('pending_approval')
        ->and(\App\Models\FabaAuditLog::query()->count())->toBe(0);
});

test('opening cannot change submitted or approved periods', function (string $status) {
    app(\App\Services\FabaRecapService::class)->setOpeningBalance(2026, 1, 'fly_ash', 100, null, $this->actor->id);
    \App\Models\FabaMonthlyApproval::query()->create(['year' => 2026, 'month' => 1, 'status' => $status]);
    expect(fn () => app(\App\Services\FabaRecapService::class)->setOpeningBalance(2026, 1, 'fly_ash', 0, null, $this->actor->id))->toThrow(\App\Exceptions\OperationalConflict::class);
    expect(\App\Models\FabaOpeningBalance::query()->first()->quantity)->toBe('100.00');
})->with(['submitted', 'approved']);

test('guest web mutation does not query tenant tables before authentication', function () {
    app(TenantService::class)->switchToPublic();
    $this->post('/waste-management/faba/production', [])->assertRedirect('/login');
});

test('document numbers include deleted rows and roll over beyond four digits', function () {
    $prefix = 'WR-TEST-2026-01';
    $record = \App\Models\WasteRecord::factory()->draft()->create(['record_number' => $prefix.'-9999']);
    $record->delete();
    $service = app(\App\Services\DocumentNumberService::class);
    $next = app(\App\Services\OperationalTransactionService::class)->run(fn () => $service->next(\App\Models\WasteRecord::class, 'record_number', $prefix));
    expect($next)->toBe($prefix.'-10000');
});

test('successful idempotent mutation replays once and rejects changed payload or permission', function () {
    app(TenantService::class)->switchToPublic();
    $payload = ['transaction_date' => '2026-01-01', 'material_type' => 'fly_ash', 'movement_type' => 'production', 'quantity' => 10, 'unit' => 'ton'];
    $first = $this->withToken($this->token)->withHeader('Idempotency-Key', 'repeat-production')->postJson('/api/v1/faba/production', $payload)->assertCreated();
    $this->withToken($this->token)->postJson('/api/v1/faba/production', $payload)->assertCreated()->assertHeader('Idempotency-Replayed', 'true')->assertExactJson($first->json());
    $this->withToken($this->token)->postJson('/api/v1/faba/production', array_merge($payload, ['quantity' => 11]))->assertConflict();
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    expect(FabaMovement::query()->count())->toBe(1)->and(\App\Models\FabaAuditLog::query()->count())->toBe(1);
    app(TenantService::class)->switchToPublic();
    $this->actor->role->permissions()->detach();
    $this->withToken($this->token)->postJson('/api/v1/faba/production', $payload)->assertForbidden();
});

test('web and api requests reserve hauling stock in one shared critical section', function () {
    $record = \App\Models\WasteRecord::factory()->approved()->create(['quantity' => 100, 'created_by' => $this->actor->id]);
    DB::unprepared('CREATE FUNCTION hauling_delay() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN PERFORM pg_sleep(1); RETURN NEW; END $$; CREATE TRIGGER hauling_delay BEFORE INSERT ON waste_haulings FOR EACH ROW EXECUTE FUNCTION hauling_delay()');
    $body = ['waste_record_id' => $record->id, 'hauling_date' => now()->toDateString(), 'quantity' => 70];
    app(TenantService::class)->switchToPublic();
    $results = runIntegrityWorkers([
        ['url' => '/waste-management/haulings', 'web_actor' => $this->actor->id, 'body' => $body],
        ['url' => '/api/v1/waste-haulings', 'token' => $this->token, 'body' => $body],
    ]);
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    expect(\App\Models\WasteHauling::query()->count())->toBe(1)
        ->and((float) \App\Models\WasteHauling::query()->sum('quantity'))->toBe(70.0);
    expect($results[0]['status'])->toBe(302);
    expect($results[1]['status'])->toBeIn([201, 422]);
});

test('direct hauling approval reloads changed quantity and stale transitions cannot overwrite terminal state', function () {
    $record = \App\Models\WasteRecord::factory()->approved()->create(['quantity' => 10]);
    $hauling = \App\Models\WasteHauling::factory()->create(['waste_record_id' => $record->id, 'quantity' => 2]);
    $other = $hauling->fresh();
    $other->update(['quantity' => 20]);
    expect(fn () => $hauling->approve($this->actor->id))->toThrow(\App\Exceptions\OperationalConflict::class);
    $other->update(['quantity' => 5]);
    expect($hauling->approve($this->actor->id))->toBeTrue()->and($other->cancel())->toBeFalse();
    expect($hauling->fresh()->status)->toBe('approved')->and($hauling->fresh()->quantity)->toBe('5.00');
});

test('later locked closing blocks historical opening changes atomically', function () {
    $recaps = app(\App\Services\FabaRecapService::class);
    $recaps->setOpeningBalance(2026, 1, 'fly_ash', 100, null, $this->actor->id);
    \App\Models\FabaMonthlyApproval::query()->create(['year' => 2026, 'month' => 2, 'status' => 'approved']);
    expect(fn () => $recaps->setOpeningBalance(2026, 1, 'fly_ash', 80, null, $this->actor->id))->toThrow(\App\Exceptions\OperationalConflict::class);
    expect(\App\Models\FabaOpeningBalance::query()->first()->quantity)->toBe('100.00');
});

test('failed snapshot generation rolls back closing approval and all audit writes', function () {
    integrityMovement();
    $period = \App\Models\FabaMonthlyApproval::query()->create(['year' => 2026, 'month' => 1, 'status' => 'submitted']);
    DB::unprepared("CREATE FUNCTION snapshot_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'synthetic snapshot failure'; END $$; CREATE TRIGGER snapshot_failure BEFORE INSERT ON faba_monthly_closing_snapshots FOR EACH ROW EXECUTE FUNCTION snapshot_failure()");
    app(TenantService::class)->switchToPublic();
    $this->withToken($this->token)->postJson('/api/v1/faba/approvals/2026/1/approve')->assertServerError();
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    expect($period->fresh()->status)->toBe('submitted')->and(\App\Models\FabaAuditLog::query()->count())->toBe(0);
});

test('improving an existing negative balance on a new date remains possible', function () {
    integrityMovement(['transaction_date' => '2026-01-01', 'stock_effect' => 'out', 'movement_type' => 'adjustment_out', 'quantity' => 100]);
    $pending = integrityMovement(['transaction_date' => '2026-01-02', 'quantity' => 50, 'approval_status' => 'pending_approval']);
    app(TenantService::class)->switchToPublic();
    $this->withToken($this->token)->postJson('/api/v1/faba/movements/'.$pending->id.'/approve')->assertOk();
});

test('concurrent identical idempotency keys create one resource and one audit', function () {
    DB::unprepared('CREATE FUNCTION production_delay() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN PERFORM pg_sleep(1); RETURN NEW; END $$; CREATE TRIGGER production_delay BEFORE INSERT ON faba_movements FOR EACH ROW EXECUTE FUNCTION production_delay()');
    app(TenantService::class)->switchToPublic();
    $input = ['url' => '/api/v1/faba/production', 'token' => $this->token, 'idempotency_key' => 'concurrent-production', 'body' => [
        'transaction_date' => '2026-01-01', 'material_type' => 'fly_ash', 'movement_type' => 'production', 'quantity' => 10, 'unit' => 'ton',
    ]];
    $results = runIntegrityWorkers([$input, $input]);
    expect(array_column($results, 'status'))->toBe([201, 201]);
    expect(count(array_filter(array_column($results, 'replayed'))))->toBe(1);
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    expect(FabaMovement::query()->count())->toBe(1)->and(\App\Models\FabaAuditLog::query()->count())->toBe(1)
        ->and(DB::table('operational_requests')->count())->toBe(1);
});

test('concurrent hauling numbers follow deleted 9999 without collisions', function () {
    $record = \App\Models\WasteRecord::factory()->approved()->create(['quantity' => 100, 'created_by' => $this->actor->id]);
    $prefix = 'WH-'.$this->organization->code.'-'.now()->format('Y-m');
    \App\Models\WasteHauling::factory()->create(['waste_record_id' => $record->id, 'hauling_number' => $prefix.'-9999', 'quantity' => 1])->delete();
    DB::unprepared('CREATE FUNCTION hauling_delay() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN PERFORM pg_sleep(1); RETURN NEW; END $$; CREATE TRIGGER hauling_delay BEFORE INSERT ON waste_haulings FOR EACH ROW EXECUTE FUNCTION hauling_delay()');
    app(TenantService::class)->switchToPublic();
    $input = ['url' => '/api/v1/waste-haulings', 'token' => $this->token, 'body' => ['waste_record_id' => $record->id, 'hauling_date' => now()->toDateString(), 'quantity' => 1]];
    $results = runIntegrityWorkers([$input, $input]);
    expect(array_column($results, 'status'))->toBe([201, 201]);
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    expect(\App\Models\WasteHauling::query()->orderBy('hauling_number')->pluck('hauling_number')->all())->toBe([$prefix.'-10000', $prefix.'-10001']);
});

test('concurrent input and closing cannot leave pending movement in submitted period', function () {
    integrityMovement();
    DB::unprepared('CREATE FUNCTION closing_delay() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN PERFORM pg_sleep(1); RETURN NEW; END $$; CREATE TRIGGER closing_delay BEFORE INSERT ON faba_movements FOR EACH ROW EXECUTE FUNCTION closing_delay(); CREATE TRIGGER closing_delay BEFORE INSERT ON faba_monthly_approvals FOR EACH ROW EXECUTE FUNCTION closing_delay()');
    app(TenantService::class)->switchToPublic();
    $results = runIntegrityWorkers([
        ['url' => '/api/v1/faba/approvals/submit', 'token' => $this->token, 'body' => ['year' => 2026, 'month' => 1]],
        ['url' => '/api/v1/faba/production', 'token' => $this->token, 'body' => ['transaction_date' => '2026-01-01', 'material_type' => 'fly_ash', 'movement_type' => 'production', 'quantity' => 10, 'unit' => 'ton']],
    ]);
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    $submitted = \App\Models\FabaMonthlyApproval::query()->where('status', 'submitted')->exists();
    expect($submitted && FabaMovement::query()->pendingApproval()->exists())->toBeFalse();
    expect($results[0]['status'])->toBe($submitted ? 200 : 409)
        ->and($results[1]['status'])->toBe($submitted ? 409 : 201);
});

test('stale soft deleted model cannot mutate but explicit restore remains supported', function () {
    $record = \App\Models\WasteRecord::factory()->draft()->create();
    $stale = $record->fresh();
    $record->delete();
    expect($stale->submitForApproval($this->actor->id))->toBeFalse();
    expect($record->restore())->toBeTrue()->and($record->fresh()->trashed())->toBeFalse();
});

test('rejected monthly operation rolls back its draft placeholder', function () {
    app(TenantService::class)->switchToPublic();
    $this->withToken($this->token)->postJson('/api/v1/faba/approvals/2026/1/approve')->assertConflict();
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    expect(\App\Models\FabaMonthlyApproval::query()->count())->toBe(0);
});

test('web rollback clears new success flash and preserves persisted stock', function () {
    integrityMovement(['transaction_date' => '2026-01-01']);
    integrityMovement(['transaction_date' => '2026-01-03', 'quantity' => 100, 'movement_type' => 'adjustment_out', 'stock_effect' => 'out']);
    $pending = integrityMovement(['transaction_date' => '2026-01-02', 'quantity' => 20, 'movement_type' => 'adjustment_out', 'stock_effect' => 'out', 'approval_status' => 'pending_approval']);
    app(TenantService::class)->switchToPublic();
    $this->actingAs($this->actor)->post(route('waste-management.faba.movements.approve', $pending->id))->assertRedirect()->assertSessionHas('error')->assertSessionMissing('success');
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    expect($pending->fresh()->approval_status)->toBe('pending_approval');
});

test('api draft waste update accepts string route identifiers and cannot change noneditable records', function () {
    $this->actor->role->permissions()->syncWithoutDetaching(\App\Models\Permission::query()->whereIn('slug', ['waste_records.edit_all', 'waste_records.edit_own'])->pluck('id')->all());
    $record = \App\Models\WasteRecord::factory()->draft()->create(['quantity' => 10, 'created_by' => $this->actor->id]);
    $body = ['date' => now()->toDateString(), 'waste_type_id' => $record->waste_type_id, 'quantity' => 12, 'unit' => $record->unit];
    app(TenantService::class)->switchToPublic();
    $this->withToken($this->token)->putJson('/api/v1/waste-records/'.$record->id, $body)->assertOk();
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    expect($record->fresh()->quantity)->toBe('12.00');
    $record->fresh()->update(['status' => 'approved']);
    app(TenantService::class)->switchToPublic();
    $this->withToken($this->token)->putJson('/api/v1/waste-records/'.$record->id, $body)->assertConflict();
});

test('idempotency refuses replay when parent record ownership changes', function () {
    $this->actor->update(['role_id' => Role::query()->where('slug', 'operator')->firstOrFail()->id]);
    $record = \App\Models\WasteRecord::factory()->approved()->create(['quantity' => 100, 'created_by' => $this->actor->id]);
    $body = ['waste_record_id' => $record->id, 'hauling_date' => now()->toDateString(), 'quantity' => 10];
    app(TenantService::class)->switchToPublic();
    $this->withToken($this->token)->withHeader('Idempotency-Key', 'owner-change')->postJson('/api/v1/waste-haulings', $body)->assertCreated();
    $other = User::factory()->create(['organization_id' => $this->organization->id]);
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    $record->update(['created_by' => $other->id]);
    app(TenantService::class)->switchToPublic();
    $this->withToken($this->token)->postJson('/api/v1/waste-haulings', $body)->assertForbidden();
});

test('same idempotency key remains separate across organizations', function () {
    app(TenantService::class)->switchToPublic();
    $payload = ['transaction_date' => '2026-01-01', 'material_type' => 'fly_ash', 'movement_type' => 'production', 'quantity' => 10];
    $first = $this->withToken($this->token)->withHeader('Idempotency-Key', 'tenant-scope')->postJson('/api/v1/faba/production', $payload)->assertCreated()->json('data.id');
    $tenants = app(TenantService::class);
    $other = Organization::factory()->create(['schema_name' => 'tenant_integrity_other']);
    $tenants->createSchema($other->schema_name);
    $tenants->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
    $tenants->switchToPublic();
    $this->actor->update(['organization_id' => $other->id]);
    $second = $this->withToken($this->token)->postJson('/api/v1/faba/production', $payload)->assertCreated()->json('data.id');
    expect($second)->not->toBe($first);
    foreach ([$this->organization, $other] as $organization) {
        $tenants->switchToSchema($organization->schema_name);
        expect(FabaMovement::query()->count())->toBe(1)->and(DB::table('operational_requests')->count())->toBe(1);
    }
});

test('failed audit removes only newly uploaded attachment and rolls back movement', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    integrityMovement();
    $vendor = \App\Models\Vendor::factory()->create();
    \Illuminate\Support\Facades\Storage::disk('local')->put('existing.pdf', 'retained');
    DB::unprepared("CREATE FUNCTION audit_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'synthetic audit failure'; END $$; CREATE TRIGGER audit_failure BEFORE INSERT ON faba_audit_logs FOR EACH ROW EXECUTE FUNCTION audit_failure()");
    app(TenantService::class)->switchToPublic();
    $this->withToken($this->token)->postJson('/api/v1/faba/utilization', [
        'transaction_date' => '2026-01-10', 'material_type' => 'fly_ash', 'movement_type' => 'utilization_external', 'quantity' => 10,
        'vendor_id' => $vendor->id, 'document_number' => 'DOC-TEST', 'document_date' => '2026-01-10',
        'attachment' => \Illuminate\Http\UploadedFile::fake()->createWithContent('new.pdf', "%PDF-1.4\nfixture"),
    ])->assertServerError();
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    expect(FabaMovement::query()->count())->toBe(1)
        ->and(\Illuminate\Support\Facades\Storage::disk('local')->allFiles())->toBe(['existing.pdf']);
});

test('atomic waste audit failure rolls back its status transition', function () {
    $record = \App\Models\WasteRecord::factory()->approved()->create(['quantity' => 100]);
    $hauling = \App\Models\WasteHauling::factory()->create(['waste_record_id' => $record->id, 'quantity' => 10]);
    $count = \App\Models\OperationalAuditLog::query()->count();
    DB::unprepared("CREATE FUNCTION audit_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'synthetic waste audit failure'; END $$; CREATE TRIGGER audit_failure BEFORE INSERT ON operational_audit_logs FOR EACH ROW EXECUTE FUNCTION audit_failure()");
    app(TenantService::class)->switchToPublic();
    $this->withToken($this->token)->postJson('/api/v1/waste-haulings/'.$hauling->id.'/approve')->assertServerError();
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    expect($hauling->fresh()->status)->toBe('pending_approval')->and(\App\Models\OperationalAuditLog::query()->count())->toBe($count);
});

test('restore cannot reactivate hauling capacity already consumed by another hauling', function () {
    $record = \App\Models\WasteRecord::factory()->approved()->create(['quantity' => 100]);
    $old = \App\Models\WasteHauling::factory()->approved()->create(['waste_record_id' => $record->id, 'quantity' => 100]);
    $old->delete();
    \App\Models\WasteHauling::factory()->approved()->create(['waste_record_id' => $record->id, 'quantity' => 100]);
    expect(fn () => $old->restore())->toThrow(\App\Exceptions\OperationalConflict::class);
    expect(\App\Models\WasteHauling::withTrashed()->findOrFail($old->id)->trashed())->toBeTrue();
    expect((float) \App\Models\WasteHauling::query()->sum('quantity'))->toBe(100.0);
});

test('direct transition audits use the explicit checker actor without an authenticated request', function () {
    $record = \App\Models\WasteRecord::factory()->create(['status' => 'pending_review', 'created_by' => null]);
    auth()->forgetGuards();
    expect($record->approve($this->actor->id))->toBeTrue();
    expect(\App\Models\OperationalAuditLog::query()->where('reference_id', $record->id)->where('action', 'update')->firstOrFail()->actor_id)->toBe($this->actor->id);
});

test('headerless faba commands work without receipt migration and keyed commands fail closed', function () {
    \Illuminate\Support\Facades\Schema::drop('operational_requests');
    app(TenantService::class)->switchToPublic();
    $payload = ['transaction_date' => '2026-01-01', 'material_type' => 'fly_ash', 'movement_type' => 'production', 'quantity' => 10];
    $this->withToken($this->token)->postJson('/api/v1/faba/production', $payload)->assertCreated();
    $this->withToken($this->token)->withHeader('Idempotency-Key', 'missing-migration')->postJson('/api/v1/faba/production', $payload)->assertServiceUnavailable();
    app(TenantService::class)->switchToSchema($this->organization->schema_name);
    expect(FabaMovement::query()->count())->toBe(1);
});

test('unchanged existing rows in a closed period remain no ops', function () {
    $opening = app(\App\Services\FabaRecapService::class)->setOpeningBalance(2026, 1, 'fly_ash', 100, null, $this->actor->id);
    $movement = integrityMovement(['quantity' => 10]);
    \App\Models\FabaMonthlyApproval::query()->create(['year' => 2026, 'month' => 1, 'status' => 'approved']);
    expect($movement->fresh()->save())->toBeTrue()->and($opening->fresh()->save())->toBeTrue();
    expect(fn () => $movement->fresh()->update(['quantity' => 11]))->toThrow(\App\Exceptions\OperationalConflict::class);
});

test('restore reloads stale quantity and status before checking capacity', function (string $oldStatus) {
    $record = \App\Models\WasteRecord::factory()->approved()->create(['quantity' => 125]);
    $old = \App\Models\WasteHauling::factory()->create(['waste_record_id' => $record->id, 'quantity' => 100, 'status' => $oldStatus]);
    $old->delete();
    DB::table('waste_haulings')->where('id', $old->id)->update(['quantity' => 150, 'status' => 'approved']);
    expect(fn () => $old->restore())->toThrow(\App\Exceptions\OperationalConflict::class);
    $persisted = \App\Models\WasteHauling::withTrashed()->findOrFail($old->id);
    expect($persisted->trashed())->toBeTrue()->and($persisted->quantity)->toBe('150.00')->and($persisted->status)->toBe('approved');
    expect(\App\Models\WasteHauling::query()->count())->toBe(0);
})->with(['pending_approval', 'approved']);

test('explicit force deletion preserves native soft delete model behavior', function () {
    $record = \App\Models\WasteRecord::factory()->draft()->create();
    $record->delete();
    expect($record->forceDelete())->toBeTrue();
    expect(\App\Models\WasteRecord::withTrashed()->find($record->id))->toBeNull();
    expect(\App\Models\OperationalAuditLog::query()->where('reference_id', $record->id)->where('action', 'force_delete')->count())->toBe(1);
});
