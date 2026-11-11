<?php

use App\Models\ApiToken;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaMonthlyClosingSnapshot;
use App\Models\FabaMonthlyClosingVersion;
use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\FabaClosingArchiveService;
use App\Services\FabaOfficialReportService;
use App\Services\FabaRecapService;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\Process\Process;

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
        'is_super_admin' => true, 'email_verified_at' => now(),
    ]);
    $this->actingAs($this->admin);
    ApiToken::create(['user_id' => $this->admin->id, 'name' => 'archive', 'token' => hash('sha256', 'archive-test'), 'expires_at' => now()->addDay()]);
    $this->tenantService->switchToSchema($this->organization->schema_name);
});

afterEach(function () {
    $this->tenantService->switchToPublic();
});

function closingVersionProduction(float $quantity = 100): void
{
    FabaMovement::factory()->create([
        'transaction_date' => '2025-01-10', 'period_year' => 2025, 'period_month' => 1,
        'movement_type' => 'production', 'stock_effect' => 'in', 'quantity' => $quantity,
        'material_type' => 'fly_ash', 'vendor_id' => null, 'purpose_id' => null,
        'internal_destination_id' => null, 'document_number' => null, 'document_date' => null,
    ]);
}

function closingVersionMigration(): object
{
    return require database_path('migrations/tenant/2026_11_11_000002_create_faba_monthly_closing_versions.php');
}

test('web closing retains v1 through reopen and appends v2 with stable explicit export', function () {
    closingVersionProduction();
    FabaMonthlyApproval::factory()->create(['year' => 2025, 'month' => 1, 'status' => 'submitted']);
    $url = fn (string $action): string => route('waste-management.faba.approvals.'.$action, [2025, 1, 'organization_id' => $this->organization->id]);
    $this->post($url('approve'))->assertRedirect()->assertSessionHasNoErrors();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(FabaMonthlyClosingVersion::count())->toBe(1);
    $first = FabaMonthlyClosingVersion::first();
    $bytes = $first->getRawOriginal('snapshot_payload');
    $context = app(FabaOfficialReportService::class)->buildContext('monthly', ['year' => 2025, 'month' => 1, 'version' => 1]);
    expect($context['title'])->toContain('Arsip Disetujui / Versi 1')
        ->and($context['summary_rows'][3]['value'])->toBe('100.00')
        ->and($context['filters']['Organisasi saat persetujuan'])->toBe($this->organization->name);
    $this->post($url('reopen'), ['reopen_note' => 'Koreksi'])->assertRedirect()->assertSessionMissing('error');
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(FabaMonthlyClosingSnapshot::count())->toBe(0)->and($first->fresh()->getRawOriginal('snapshot_payload'))->toBe($bytes);
    closingVersionProduction(30);
    $this->organization->update(['name' => 'Nama Baru']);
    $this->admin->update(['name' => 'Nama Pemeriksa Baru']);
    expect(app(FabaOfficialReportService::class)->buildContext('monthly', ['year' => 2025, 'month' => 1, 'version' => 1]))->toBe($context);
    $this->post(route('waste-management.faba.approvals.submit', ['organization_id' => $this->organization->id]), ['year' => 2025, 'month' => 1])->assertRedirect()->assertSessionMissing('error');
    $reviewer = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $this->admin->role_id, 'is_super_admin' => true, 'email_verified_at' => now()]);
    $this->actingAs($reviewer);
    $this->post($url('approve'))->assertRedirect()->assertSessionMissing('error');
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(FabaMonthlyClosingVersion::count())->toBe(2)
        ->and(app(FabaOfficialReportService::class)->buildContext('monthly', ['year' => 2025, 'month' => 1, 'version' => 2])['summary_rows'][3]['value'])->toBe('130.00');
    $this->get(route('waste-management.faba.reports.index', ['year' => 2025, 'month' => 1, 'organization_id' => $this->organization->id]))->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('archiveAvailable', true)->has('closingVersions', 2));
});

test('api retries replay approval without appending duplicate archive and reopen keeps evidence', function () {
    closingVersionProduction();
    FabaMonthlyApproval::factory()->create(['year' => 2025, 'month' => 1, 'status' => 'submitted']);
    $this->withToken('archive-test')->withHeader('Idempotency-Key', 'closing-v1')
        ->postJson('/api/v1/faba/approvals/2025/1/approve')->assertOk();
    $this->withToken('archive-test')->withHeader('Idempotency-Key', 'closing-v1')
        ->postJson('/api/v1/faba/approvals/2025/1/approve')->assertOk()->assertHeader('Idempotency-Replayed', 'true');
    $this->flushHeaders();
    $this->withToken('archive-test')->postJson('/api/v1/faba/approvals/2025/1/reopen', ['reopen_note' => 'Koreksi'])->assertOk();
    $this->withToken('archive-test')->getJson('/api/v1/faba/approvals/2025/1')->assertOk()
        ->assertJsonPath('data.snapshot', null)->assertJsonPath('data.archive_available', true)->assertJsonCount(1, 'data.closing_versions');
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(FabaMonthlyClosingVersion::count())->toBe(1);
});

test('web and api export selected stored version and reject invalid or missing versions', function () {
    closingVersionProduction();
    app(FabaRecapService::class)->storeMonthlyClosingSnapshot(2025, 1, $this->admin->id);
    foreach (['xlsx', 'pdf'] as $format) {
        $web = route('waste-management.faba.reports.monthly.'.$format, ['year' => 2025, 'month' => 1, 'version' => 1, 'organization_id' => $this->organization->id]);
        $this->get($web)->assertOk()->assertDownload('faba-monthly-recap-2025-01-v1.'.$format);
        $this->withToken('archive-test')->get('/api/v1/faba/reports/monthly.'.$format.'?year=2025&month=1&version=1')
            ->assertOk()->assertDownload('faba-monthly-recap-2025-01-v1.'.$format);
    }
    foreach (['0', 'bad', '1.5'] as $version) {
        $this->getJson(route('waste-management.faba.reports.monthly.pdf', ['year' => 2025, 'month' => 1, 'version' => $version, 'organization_id' => $this->organization->id]))->assertUnprocessable();
        $this->withToken('archive-test')->getJson('/api/v1/faba/reports/monthly.pdf?year=2025&month=1&version='.$version)->assertUnprocessable();
    }
    $this->withToken('archive-test')->getJson('/api/v1/faba/reports/monthly.pdf?version=1')->assertUnprocessable();
    $this->withToken('archive-test')->getJson('/api/v1/faba/reports/monthly.pdf?year=2025&month=1&version=2')->assertNotFound();
});

test('legacy migration preserves exact payload bytes and does not invent historical fields', function () {
    closingVersionMigration()->down();
    $payload = '{ "recap" : {"closing_balance": 72.10}, "calculation_version": 1 }';
    $id = (string) \Illuminate\Support\Str::uuid();
    DB::table('faba_monthly_closing_snapshots')->insert([
        'id' => $id, 'year' => 2024, 'month' => 12, 'status' => 'approved',
        'snapshot_payload' => $payload, 'warning_summary' => ' [ ] ',
        'approved_by' => $this->admin->id, 'approved_at' => '2024-12-31 18:00:00',
    ]);
    closingVersionMigration()->up();
    $row = DB::table('faba_monthly_closing_versions')->first();
    expect($row->snapshot_payload)->toBe($payload)->and($row->warning_summary)->toBe(' [ ] ')
        ->and(DB::table('faba_monthly_closing_snapshots')->value('snapshot_payload'))->toBe($payload);
    $context = app(FabaOfficialReportService::class)->buildContext('monthly', ['year' => 2024, 'month' => 12, 'version' => 1]);
    expect($context['summary_rows'][3]['value'])->toBe('72.10')->and($context['summary_rows'][0]['value'])->toBe('Tidak tercatat')
        ->and($context['reconciliation_rows'])->toBe([])->and($context['filters']['Organisasi saat persetujuan'])->toBe('Tidak tercatat pada snapshot lama');
    expect(view('faba.reports.official', $context)->render())->toContain('tidak dihitung ulang');
    expect(fn () => closingVersionMigration()->down())->toThrow(RuntimeException::class);
});

test('missing archive schema preserves old approvals and draft exports but fails explicit archive requests', function () {
    closingVersionMigration()->down();
    closingVersionProduction();
    FabaMonthlyApproval::factory()->create(['year' => 2025, 'month' => 1, 'status' => 'submitted']);
    $this->withToken('archive-test')->postJson('/api/v1/faba/approvals/2025/1/approve')->assertOk();
    $this->withToken('archive-test')->getJson('/api/v1/faba/approvals/2025/1')->assertOk()
        ->assertJsonPath('data.archive_available', false)->assertJsonCount(0, 'data.closing_versions')->assertJsonPath('data.snapshot.status', 'approved');
    $this->withToken('archive-test')->getJson('/api/v1/faba/reports/monthly.pdf?year=2025&month=1&version=1')->assertStatus(503);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(app(FabaOfficialReportService::class)->buildContext('monthly', ['year' => 2025, 'month' => 1])['title'])->toContain('Draft / Data Langsung');
    $this->get(route('waste-management.faba.reports.monthly.xlsx', ['year' => 2025, 'month' => 1, 'organization_id' => $this->organization->id]))->assertOk();
    $this->withToken('archive-test')->postJson('/api/v1/faba/approvals/2025/1/reopen', ['reopen_note' => 'Koreksi'])->assertOk();
});

test('archive is tenant isolated and does not leak versions from another schema', function () {
    closingVersionProduction();
    app(FabaRecapService::class)->storeMonthlyClosingSnapshot(2025, 1, $this->admin->id);
    $this->tenantService->switchToPublic();
    $other = Organization::factory()->create();
    $this->tenantService->createSchema($other->schema_name);
    $this->tenantService->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
    $this->tenantService->switchToSchema($other->schema_name);
    expect(app(FabaClosingArchiveService::class)->versions(2025, 1))->toBe([]);
    expect(fn () => app(FabaClosingArchiveService::class)->find(2025, 1, 1))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

test('archive disallows model and raw sql mutation or truncation', function () {
    closingVersionProduction();
    app(FabaRecapService::class)->storeMonthlyClosingSnapshot(2025, 1, $this->admin->id);
    $version = FabaMonthlyClosingVersion::first();
    expect(fn () => $version->update(['version' => 2]))->toThrow(LogicException::class);
    expect(fn () => $version->delete())->toThrow(LogicException::class);
    foreach (['UPDATE faba_monthly_closing_versions SET version = 2', 'DELETE FROM faba_monthly_closing_versions', 'TRUNCATE faba_monthly_closing_versions'] as $sql) {
        expect(fn () => DB::transaction(fn () => DB::statement($sql)))->toThrow(\Illuminate\Database\QueryException::class);
    }
    expect(FabaMonthlyClosingVersion::count())->toBe(1);
});

function runClosingVersionWorkers(array $requests): array
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

test('competing approval requests append only one approved version', function () {
    closingVersionProduction();
    FabaMonthlyApproval::factory()->create(['year' => 2025, 'month' => 1, 'status' => 'submitted']);
    DB::unprepared('CREATE FUNCTION archive_delay() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN PERFORM pg_sleep(1); RETURN NEW; END $$; CREATE TRIGGER archive_delay BEFORE INSERT ON faba_monthly_closing_versions FOR EACH ROW EXECUTE FUNCTION archive_delay()');
    $this->tenantService->switchToPublic();
    $results = runClosingVersionWorkers([
        ['url' => '/api/v1/faba/approvals/2025/1/approve', 'token' => 'archive-test'],
        ['url' => '/api/v1/faba/approvals/2025/1/approve', 'token' => 'archive-test'],
    ]);
    $statuses = array_column($results, 'status');
    sort($statuses);
    expect($statuses)->toBe([200, 409]);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect(FabaMonthlyClosingVersion::count())->toBe(1)->and(FabaMonthlyClosingSnapshot::count())->toBe(1);
});

test('archive insert failure atomically rolls back approval and mutable projection', function () {
    closingVersionProduction();
    $approval = FabaMonthlyApproval::factory()->create(['year' => 2025, 'month' => 1, 'status' => 'submitted']);
    DB::unprepared("CREATE FUNCTION archive_fail() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'archive test failure'; END $$; CREATE TRIGGER archive_fail BEFORE INSERT ON faba_monthly_closing_versions FOR EACH ROW EXECUTE FUNCTION archive_fail()");
    $this->withToken('archive-test')->postJson('/api/v1/faba/approvals/2025/1/approve')->assertStatus(500);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    expect($approval->fresh()->status)->toBe('submitted')->and(FabaMonthlyClosingVersion::count())->toBe(0)->and(FabaMonthlyClosingSnapshot::count())->toBe(0);
});
