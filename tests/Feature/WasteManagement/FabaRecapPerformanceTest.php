<?php

use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\FabaRecapService;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->organization = Organization::factory()->create();
    $this->tenants = app(TenantService::class);
    $this->tenants->createSchema($this->organization->schema_name);
    $this->tenants->runMigrationsForTenant($this->organization->schema_name, 'database/migrations/tenant');
    $this->tenants->switchToPublic();
    $this->admin = User::factory()->create([
        'organization_id' => $this->organization->id,
        'role_id' => Role::query()->where('slug', 'super_admin')->value('id'),
        'is_super_admin' => true,
        'email_verified_at' => now(),
    ]);
    $this->actingAs($this->admin);
    $this->tenants->switchToSchema($this->organization->schema_name);
});

afterEach(function () {
    DB::disableQueryLog();
    DB::flushQueryLog();
    $this->tenants->switchToPublic();
});

function performanceFabaMovement(float $quantity = 100, int $month = 1, array $attributes = []): FabaMovement
{
    return FabaMovement::factory()->create([
        'transaction_date' => sprintf('2025-%02d-02', $month),
        'period_year' => 2025,
        'period_month' => $month,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'quantity' => $quantity,
        'vendor_id' => null,
        'internal_destination_id' => null,
        'purpose_id' => null,
        'document_number' => null,
        'document_date' => null,
        ...$attributes,
    ]);
}

test('monthly grouped totals retain quantities directions counts and soft delete filtering', function () {
    performanceFabaMovement(100, attributes: ['movement_type' => 'opening_balance']);
    performanceFabaMovement(10.11);
    performanceFabaMovement(20.22);
    performanceFabaMovement(3.33, attributes: ['movement_type' => 'workshop', 'material_type' => 'bottom_ash']);
    performanceFabaMovement(4.44, attributes: ['movement_type' => 'reject']);
    performanceFabaMovement(1.11, attributes: ['movement_type' => 'reject', 'stock_effect' => 'out']);
    performanceFabaMovement(2.22, attributes: ['movement_type' => 'disposal_pok', 'stock_effect' => 'out']);
    performanceFabaMovement(5.55, attributes: ['movement_type' => 'utilization_internal', 'stock_effect' => 'out']);
    performanceFabaMovement(999, attributes: ['approval_status' => 'draft']);
    performanceFabaMovement(888)->delete();

    DB::enableQueryLog();
    $recap = app(FabaRecapService::class)->getMonthlyRecap(2025, 1);
    $queries = DB::getQueryLog();

    expect($recap['total_production'])->toBe(33.66)
        ->and($recap['total_utilization'])->toBe(5.55)
        ->and($recap['production_movements_count'])->toBe(6)
        ->and($recap['utilization_movements_count'])->toBe(1)
        ->and($recap['closing_fly_ash'])->toBe(125.89)
        ->and($recap['closing_bottom_ash'])->toBe(3.33)
        ->and($recap['movement_summary']['inflow_fly_ash'])->toBe(34.77)
        ->and($recap['movement_summary']['outflow_fly_ash'])->toBe(8.88)
        ->and(count($queries))->toBeLessThanOrEqual(16);
});

test('recaps are reused only inside a read snapshot and are fresh after a write', function () {
    performanceFabaMovement();
    $service = app(FabaRecapService::class);

    $service->withRecapSnapshot(function () use ($service) {
        $first = $service->getMonthlyRecap(2025, 1);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $second = $service->getMonthlyRecap(2025, 1);
        expect($second)->toBe($first)
            ->and(DB::getQueryLog())->toHaveCount(1);
        DB::disableQueryLog();
    });

    performanceFabaMovement(25);
    expect($service->getMonthlyRecap(2025, 1)['closing_balance'])->toBe(125.0)
        ->and($service->withRecapSnapshot(fn (): array => $service->getMonthlyRecap(2025, 1))['closing_balance'])->toBe(125.0);
});

test('snapshot keys follow the actual tenant schema even when one service is reused', function () {
    performanceFabaMovement(100);
    $this->tenants->switchToPublic();
    $other = Organization::factory()->create();
    $this->tenants->createSchema($other->schema_name);
    $this->tenants->runMigrationsForTenant($other->schema_name, 'database/migrations/tenant');
    $this->tenants->switchToSchema($other->schema_name);
    performanceFabaMovement(25);
    $this->tenants->switchToSchema($this->organization->schema_name);
    $service = app(FabaRecapService::class);

    $service->withRecapSnapshot(function () use ($service, $other) {
        expect($service->getMonthlyRecap(2025, 1)['closing_balance'])->toBe(100.0);
        DB::statement(sprintf('SET search_path TO "%s", public', $other->schema_name));
        expect($service->getMonthlyRecap(2025, 1)['closing_balance'])->toBe(25.0);
        $this->tenants->switchToSchema($this->organization->schema_name);
        expect($service->getMonthlyRecap(2025, 1)['closing_balance'])->toBe(100.0);
    });
});

test('failed dashboard rendering discards its recap snapshot', function () {
    performanceFabaMovement();
    $service = app(FabaRecapService::class);
    expect(fn () => $service->withRecapSnapshot(function () use ($service): void {
        $service->getMonthlyRecap(2025, 1);
        throw new RuntimeException('Failed rendering');
    }))->toThrow(RuntimeException::class, 'Failed rendering');

    performanceFabaMovement(50);
    expect($service->getMonthlyRecap(2025, 1)['closing_balance'])->toBe(150.0);
});

test('dashboard bounds monthly FABA aggregation queries across a full year', function (string $routeName, string $balancePath) {
    foreach (range(1, 12) as $month) {
        performanceFabaMovement(10, $month);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();
    $response = $this->get(route($routeName, [
        'organization_id' => $this->organization->id,
        'year' => 2025,
        'month' => '2025-12',
    ]));
    $queries = collect(DB::getQueryLog());
    $monthlyAggregates = $queries->filter(fn (array $query): bool => str_contains($query['query'], 'COUNT(*) as movements_count'));
    $fabaQueries = $queries->filter(fn (array $query): bool => str_contains($query['query'], '"faba_movements"'));

    $response->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where($balancePath, 120));
    /**
     * The original service used 1,892 / 1,361 FABA queries for these fixtures.
     * The optimized responses use 238 / 230, including shared notifications.
     * 250 is the post-change regression ceiling, not a claim of fewer than 190.
     */
    expect($monthlyAggregates->count())->toBeLessThanOrEqual(16)
        ->and($fabaQueries->count())->toBeLessThanOrEqual(250);
})->with([
    'FABA dashboard' => ['waste-management.faba.dashboard', 'stats.current_balance'],
    'main dashboard' => ['dashboard', 'fabaStats.current_balance'],
]);
