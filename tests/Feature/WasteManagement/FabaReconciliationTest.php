<?php

use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\FabaOfficialReportService;
use App\Services\FabaRecapService;
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

function reconciliationMovement(string $date, string $type, string $effect, float $quantity, string $material = 'fly_ash'): void
{
    FabaMovement::factory()->create([
        'transaction_date' => $date, 'period_year' => (int) substr($date, 0, 4),
        'period_month' => (int) substr($date, 5, 2), 'movement_type' => $type,
        'stock_effect' => $effect, 'quantity' => $quantity, 'material_type' => $material,
        'vendor_id' => null, 'internal_destination_id' => null, 'purpose_id' => null,
        'document_number' => null, 'document_date' => null,
    ]);
}

test('dashboard balance follows the selected year instead of the latest global balance', function () {
    reconciliationMovement('2024-01-01', 'production', 'in', 100);
    reconciliationMovement('2025-01-01', 'production', 'in', 50);
    foreach ([2024 => 100, 2025 => 150] as $year => $balance) {
        $this->get(route('waste-management.faba.dashboard', ['organization_id' => $this->organization->id, 'year' => $year]))
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.current_balance', $balance)
            ->where('stats.balance_as_of_date', "$year-12-31"));
    }
});

test('monthly reconciliation accounts for every movement type and actual stock direction', function () {
    reconciliationMovement('2025-01-01', 'opening_balance', 'in', 100);
    foreach ([['production', 'in', 20], ['workshop', 'in', 10], ['adjustment_in', 'in', 9], ['adjustment_out', 'out', 3], ['reject', 'in', 7], ['reject', 'out', 4], ['disposal_pok', 'out', 5], ['utilization_external', 'out', 8], ['utilization_internal', 'out', 6]] as [$type, $effect, $quantity]) {
        reconciliationMovement('2025-01-02', $type, $effect, $quantity);
        reconciliationMovement('2025-01-02', $type, $effect, $quantity / 2, 'bottom_ash');
    }
    reconciliationMovement('2025-01-03', 'production', 'in', 999);
    FabaMovement::query()->where('quantity', 999)->update(['approval_status' => 'draft']);
    $recap = app(FabaRecapService::class)->getMonthlyRecap(2025, 1);
    expect($recap['closing_fly_ash'])->toBe(120.0)->and($recap['closing_bottom_ash'])->toBe(10.0)
        ->and($recap['balance_components'])->toHaveCount(9);
    foreach (['fly_ash', 'bottom_ash'] as $material) {
        $rows = collect($recap['balance_components']);
        expect(round($recap['opening_'.$material] + $rows->where('stock_effect', 'in')->sum($material) - $rows->where('stock_effect', 'out')->sum($material), 2))->toBe($recap['closing_'.$material]);
    }
    $context = app(FabaOfficialReportService::class)->buildContext('monthly', ['year' => 2025, 'month' => 1]);
    expect($context['reconciliation_rows'])->toHaveCount(11);
    expect(view('faba.reports.official', $context)->render())->toContain('Penyesuaian Masuk', 'Disposal POK', 'Rekonsiliasi Saldo');
});

test('yearly reconciliation exposes later opening resets without counting them as production', function () {
    reconciliationMovement('2025-01-01', 'opening_balance', 'in', 100);
    reconciliationMovement('2025-01-02', 'production', 'in', 10);
    reconciliationMovement('2025-02-01', 'opening_balance', 'in', 80);
    reconciliationMovement('2025-02-02', 'adjustment_out', 'out', 5);
    $totals = app(FabaRecapService::class)->getYearlyRecap(2025)['totals'];
    expect($totals['opening_balance'])->toBe(100.0)->and($totals['opening_reset_total'])->toBe(-30.0)
        ->and($totals['total_production'])->toBe(10.0)->and($totals['closing_balance'])->toBe(75.0);
    expect($totals['opening_balance'] + $totals['movement_summary']['inflow_fly_ash'] - $totals['movement_summary']['outflow_fly_ash'] + $totals['opening_reset_total'])->toBe($totals['closing_balance']);
    $context = app(FabaOfficialReportService::class)->buildContext('yearly', ['year' => 2025]);
    expect(view('faba.reports.official', $context)->render())->toContain('Reset Saldo Awal Antarbulan', '-30.00');
});

test('adjustment inflows are valid stock sources for utilization warnings', function () {
    reconciliationMovement('2025-01-01', 'adjustment_in', 'in', 100);
    reconciliationMovement('2025-01-02', 'utilization_internal', 'out', 50);
    $warnings = collect(app(FabaRecapService::class)->getMonthlyRecap(2025, 1)['warnings'])->pluck('code');
    expect($warnings)->not->toContain('utilization_exceeds_stock', 'utilization_without_stock_source');
});

test('duplicate warnings distinguish dates and stock directions but catch same day duplicates', function () {
    reconciliationMovement('2025-01-01', 'production', 'in', 10);
    reconciliationMovement('2025-01-02', 'production', 'in', 10);
    reconciliationMovement('2025-01-03', 'reject', 'in', 2);
    reconciliationMovement('2025-01-03', 'reject', 'out', 2);
    $service = app(FabaRecapService::class);
    expect(collect($service->getMonthlyRecap(2025, 1)['warnings'])->pluck('code'))->not->toContain('duplicate_warning');
    reconciliationMovement('2025-01-02', 'production', 'in', 10);
    expect(collect($service->getMonthlyRecap(2025, 1)['warnings'])->pluck('code'))->toContain('duplicate_warning');
});

test('empty year reconciliation has stable zero balances and no phantom components', function () {
    $service = app(FabaRecapService::class);
    $monthly = $service->getMonthlyRecap(2025, 1);
    $totals = $service->getYearlyRecap(2025)['totals'];
    expect($monthly['balance_components'])->toBe([])->and($monthly['closing_balance'])->toBe(0.0)
        ->and($totals['balance_components'])->toBe([])->and($totals['opening_reset_total'])->toBe(0.0)
        ->and($totals['closing_balance'])->toBe(0.0)->and($totals['movement_summary']['inflow_fly_ash'])->toBe(0.0);
});
