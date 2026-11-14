<?php

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use App\Services\TenantService;
use App\Services\WasteReportService;
use Inertia\Testing\AssertableInertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
    $this->tenantService->switchToSchema($this->organization->schema_name);
    $this->filters = ['start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'year' => 2026];
});

afterEach(function () {
    $this->tenantService->switchToPublic();
});

function b3ReportUrl(string $name = 'index', array $filters = []): string
{
    return route('waste-management.reports.'.$name, [
        'organization_id' => test()->organization->id, ...test()->filters, ...$filters,
    ]);
}

/**
 * Run memory bounds in a fresh PHP process, not after the suite's retained caches.
 * Use the supported Pest CLI because runtime fluent calls cannot enable isolation.
 */
function runB3ReportMemoryTestInFreshProcess(string $description): bool
{
    if (getenv('WASPRO_REPORT_MEMORY_TEST') === $description) {
        return false;
    }

    $process = new Process([
        PHP_BINARY, base_path('vendor/bin/pest'), __FILE__, '--compact', '--colors=never', '--filter', $description,
    ], base_path(), ['WASPRO_REPORT_MEMORY_TEST' => $description], timeout: 120);
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getOutput().$process->getErrorOutput())
        ->and($process->getOutput())->toMatch('/Tests:\s+1 passed/');

    return true;
}

test('b3 report centre exposes approved scoped balances and normalized filters', function () {
    WasteRecord::factory()->approved()->create(['created_by' => $this->admin->id, 'date' => '2026-10-01', 'quantity' => 2457, 'unit' => 'kg']);
    WasteRecord::factory()->create(['created_by' => $this->admin->id, 'date' => '2026-10-02', 'quantity' => 10, 'status' => 'draft']);
    $this->get(b3ReportUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('waste-management/reports/Index')->has('report.rows', 1)
        ->where('report.organization', $this->organization->name)
        ->where('report.totals.0.incoming', 2457)->where('filters.year', 2026));
});

test('storage report carries previous balance and counts approved actual hauling only', function () {
    $record = WasteRecord::factory()->approved()->create([
        'created_by' => $this->admin->id, 'date' => '2026-09-01', 'quantity' => 2457, 'unit' => 'kg', 'source' => null,
    ]);
    foreach ([['2026-09-20', 457, 'approved'], ['2026-10-15', 500, 'approved'], ['2026-10-20', 1000, 'pending_approval']] as [$date, $amount, $status]) {
        WasteHauling::factory()->create(['waste_record_id' => $record->id, 'created_by' => $this->admin->id, 'hauling_date' => $date, 'quantity' => $amount, 'unit' => 'kg', 'status' => $status]);
    }
    $report = app(WasteReportService::class)->storageReport($this->admin, $this->filters);
    expect($report['entries'])->toHaveCount(2)
        ->and($report['entries'][0]['kind'])->toBe('Saldo awal periode')
        ->and($report['entries'][0]['incoming'])->toBe(0)
        ->and($report['entries'][0]['remaining'])->toBe(2.0)
        ->and($report['entries'][1]['outgoing'])->toBe(0.5)
        ->and($report['entries'][1]['remaining'])->toBe(1.5)
        ->and($report['entries'][1]['source'])->toBe('Tidak tercatat');
});

test('annual report separates nonmass units and reconciles monthly incoming and outgoing without fabricated utilization', function () {
    $record = WasteRecord::factory()->approved()->create(['created_by' => $this->admin->id, 'date' => '2025-12-31', 'quantity' => 1000, 'unit' => 'kg']);
    WasteRecord::factory()->approved()->create(['created_by' => $this->admin->id, 'waste_type_id' => $record->waste_type_id, 'date' => '2026-10-01', 'quantity' => 2000, 'unit' => 'kg']);
    WasteRecord::factory()->approved()->create(['created_by' => $this->admin->id, 'waste_type_id' => $record->waste_type_id, 'date' => '2026-10-01', 'quantity' => 50, 'unit' => 'liter']);
    WasteHauling::factory()->approved()->create(['waste_record_id' => $record->id, 'created_by' => $this->admin->id, 'hauling_date' => '2026-10-31', 'quantity' => 500, 'unit' => 'kg']);
    $report = app(WasteReportService::class)->annualReport($this->admin, $this->filters);
    $ton = collect($report['annual_rows'])->firstWhere('unit', 'ton');
    $liters = collect($report['annual_rows'])->firstWhere('unit', 'liter');
    expect($report['annual_rows'])->toHaveCount(2)
        ->and($ton['opening'])->toBe(1.0)->and($ton['incoming'])->toBe(2.0)
        ->and($ton['outgoing'])->toBe(0.5)->and($ton['closing'])->toBe(2.5)
        ->and($ton['months'][9]['incoming'])->toBe(2.0)->and($ton['months'][9]['outgoing'])->toBe(0.5)
        ->and($ton['balance_percent'])->toBe(100.0)
        ->and($liters['incoming'])->toBe(50.0)
        ->and($report['warnings'][0])->toContain('belum memisahkan pemanfaatan');
});

test('b3 report zero denominator does not create a division error', function () {
    $report = app(WasteReportService::class)->annualReport($this->admin, $this->filters);
    expect($report['annual_rows'])->toBe([]);
    $this->get(b3ReportUrl('balance.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('b3 report exports valid xlsx with literal unsafe-looking text and unsigned pdf', function () {
    $record = WasteRecord::factory()->approved()->create(['created_by' => $this->admin->id, 'date' => '2026-10-01', 'quantity' => 2457, 'unit' => 'kg', 'source' => '=HYPERLINK("https://example.invalid")']);
    $record->wasteType->update(['name' => '=1+1']);
    foreach (['storage.xlsx', 'balance.xlsx'] as $name) {
        $response = $this->get(b3ReportUrl($name));
        $response->assertOk();
        if (getenv('WASPRO_REPORT_VISUAL_QA') === 'true') {
            copy($response->baseResponse->getFile()->getPathname(), sys_get_temp_dir().'/waspro-b3-'.$name);
        }
        $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
        expect($book->getActiveSheet()->getPageSetup()->getOrientation())->toBe('landscape')
            ->and($book->getActiveSheet()->getPageSetup()->getFitToWidth())->toBe(1)
            ->and($book->getActiveSheet()->getColumnDimension('B')->getWidth())->toBeGreaterThan(8);
        expect($book->getActiveSheet()->getStyle($name === 'storage.xlsx' ? 'A7' : 'A5')->getBorders()->getBottom()->getBorderStyle())->toBe('thin');
        $values = $book->getActiveSheet()->toArray();
        $numericValues = [];
        foreach ($book->getActiveSheet()->getCoordinates() as $coordinate) {
            $cell = $book->getActiveSheet()->getCell($coordinate);
            if ($cell->getDataType() === 'n') {
                $numericValues[] = (float) $cell->getValue();
            }
        }
        expect($numericValues)->toContain(2.457);
        expect(collect($values)->flatten()->contains(fn ($value): bool => str_contains((string) $value, '=1+1')))->toBeTrue();
        foreach ($book->getActiveSheet()->getCoordinates() as $coordinate) {
            expect($book->getActiveSheet()->getCell($coordinate)->getDataType())->not->toBe('f');
        }
        $book->disconnectWorksheets();
    }
    foreach (['storage.pdf', 'balance.pdf'] as $name) {
        $response = $this->get(b3ReportUrl($name))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        if (getenv('WASPRO_REPORT_VISUAL_QA') === 'true') {
            file_put_contents(sys_get_temp_dir().'/waspro-b3-'.$name, $response->getContent());
        }
    }
});

test('b3 reports reject invalid dates and years', function () {
    $this->get(b3ReportUrl('storage.pdf', ['end_date' => '2026-09-30']))->assertSessionHasErrors('end_date');
    $this->get(b3ReportUrl('balance.xlsx', ['year' => 1999]))->assertSessionHasErrors('year');
    $this->get(b3ReportUrl('storage.pdf', ['document_date' => 'bad-date']))->assertSessionHasErrors('document_date');
});

test('b3 own-only role cannot export colleagues records', function () {
    $this->tenantService->switchToPublic();
    $role = Role::factory()->create();
    $role->permissions()->attach(Permission::query()->where('slug', 'waste_records.view_own')->value('id'));
    $operator = User::factory()->create(['organization_id' => $this->organization->id, 'role_id' => $role->id, 'is_super_admin' => false, 'email_verified_at' => now()]);
    $this->tenantService->switchToSchema($this->organization->schema_name);
    WasteRecord::factory()->approved()->create(['created_by' => $this->admin->id, 'date' => '2026-10-01', 'quantity' => 999]);
    WasteRecord::factory()->approved()->create(['created_by' => $operator->id, 'date' => '2026-10-01', 'quantity' => 5]);
    $this->actingAs($operator);
    $this->get(b3ReportUrl())->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->has('report.rows', 1)->where('report.totals.0.incoming', 5));
    $report = app(WasteReportService::class)->annualReport($operator, $this->filters);
    expect($report['annual_rows'])->toHaveCount(1);
});

test('b3 report uses PLN branding only for explicitly configured organization identity', function () {
    $service = app(WasteReportService::class);
    expect($service->context($this->admin, $this->filters)['pln_branding'])->toBeFalse();
    config(['waste-reports.pln_organization_ids' => [$this->organization->id]]);
    expect($service->context($this->admin, $this->filters)['pln_branding'])->toBeTrue();
});

test('operator can export storage reports and cannot switch tenant', function () {
    $this->tenantService->switchToPublic();
    $operator = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::query()->where('slug', 'operator')->value('id'),
        'is_super_admin' => false, 'email_verified_at' => now(),
    ]);
    $other = Organization::factory()->create();
    $this->tenantService->switchToSchema($this->organization->schema_name);
    WasteRecord::factory()->approved()->count(45)->create(['waste_type_id' => \App\Models\WasteType::factory()->create()->id, 'created_by' => $operator->id, 'date' => '2026-10-01']);
    $this->actingAs($operator);
    $this->get(b3ReportUrl('storage.xlsx'))->assertOk();
    $this->get(b3ReportUrl('storage.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    foreach (['storage.xlsx', 'storage.pdf', 'balance.xlsx', 'balance.pdf'] as $name) {
        $this->get(b3ReportUrl($name, ['organization_id' => $other->id]))->assertForbidden();
    }
});

test('large PDF export returns actionable validation rather than rendering an unbounded table', function () {
    if (runB3ReportMemoryTestInFreshProcess('large PDF export returns actionable validation rather than rendering an unbounded table')) {
        return;
    }

    $previousLimit = ini_get('memory_limit');
    ini_set('memory_limit', '128M');
    try {
        expect(ini_get('memory_limit'))->toBe('128M');
        WasteRecord::factory()->approved()->count(151)->create(['waste_type_id' => \App\Models\WasteType::factory()->create()->id, 'created_by' => $this->admin->id, 'date' => '2026-10-01']);
        $this->from(b3ReportUrl())->get(b3ReportUrl('storage.pdf'))->assertRedirect(b3ReportUrl())->assertSessionHasErrors('export');
        $this->get(b3ReportUrl('storage.xlsx'))->assertOk();
    } finally {
        ini_set('memory_limit', $previousLimit);
    }
});

test('storage PDF renders at the row limit under 128 megabytes', function () {
    if (runB3ReportMemoryTestInFreshProcess('storage PDF renders at the row limit under 128 megabytes')) {
        return;
    }

    $previousLimit = ini_get('memory_limit');
    ini_set('memory_limit', '128M');
    try {
        config(['waste-reports.pln_organization_ids' => [$this->organization->id]]);
        WasteRecord::factory()->approved()->count(150)->create([
            'waste_type_id' => \App\Models\WasteType::factory()->create()->id,
            'created_by' => $this->admin->id, 'date' => '2026-10-01',
        ]);
        expect(ini_get('memory_limit'))->toBe('128M');
        $response = $this->get(b3ReportUrl('storage.pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        if (getenv('WASPRO_REPORT_VISUAL_QA') === 'true') {
            file_put_contents(sys_get_temp_dir().'/waspro-b3-storage-150.pdf', $response->getContent());
        }
    } finally {
        ini_set('memory_limit', $previousLimit);
    }
});

test('reports use structured source names with unchanged historical fallback', function () {
    $source = \App\Models\WasteSource::create(['name' => 'Workshop BSR', 'is_active' => true]);
    $new = WasteRecord::factory()->approved()->create(['created_by' => $this->admin->id, 'date' => '2026-10-01', 'source_id' => $source->id, 'source' => 'Teks asal lama']);
    $old = WasteRecord::factory()->approved()->create(['created_by' => $this->admin->id, 'date' => '2026-10-02', 'source' => 'Sumber historis']);
    $source->delete();
    $storage = app(WasteReportService::class)->storageReport($this->admin, $this->filters);
    expect(collect($storage['entries'])->pluck('source')->all())->toContain('Workshop BSR', 'Sumber historis');
    $annual = app(WasteReportService::class)->annualReport($this->admin, $this->filters);
    expect(collect($annual['annual_rows'])->pluck('sources')->all())->toContain('Workshop BSR', 'Sumber historis');
    expect($new->fresh()->source)->toBe('Teks asal lama')->and($old->fresh()->source)->toBe('Sumber historis');
});
