<?php

use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WasteCategory;
use App\Models\WasteCharacteristic;
use App\Models\WasteRecord;
use App\Models\WasteTransportation;
use App\Models\WasteType;
use App\Services\TenantService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia;

function migrateTenantSchema(Organization $organization): void
{
    $tenantService = app(TenantService::class);

    if (! $tenantService->schemaExists($organization->schema_name)) {
        $tenantService->createSchema($organization->schema_name);
    }

    $tenantService->switchToSchema($organization->schema_name);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--force' => true,
    ]);
    $tenantService->switchToPublic();
}

function createDashboardWasteType(int $storagePeriodDays): WasteType
{
    return WasteType::factory()->create([
        'storage_period_days' => $storagePeriodDays,
    ]);
}

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $organization = Organization::factory()->create([
        'code' => 'DASHGEN',
        'schema_name' => 'tenant_dashboard_general',
    ]);
    migrateTenantSchema($organization);

    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'email_verified_at' => now(),
    ]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->has('organizationName')
            ->has('notificationCount')
            ->has('headerRiskLabel')
            ->has('headerRiskTone')
            ->has('stats')
            ->has('pendingApprovals')
            ->has('wasteByCategory')
            ->has('fabaProductionMaterialDistribution')
            ->has('transportationByStatus')
            ->has('fabaStats')
            ->has('fabaChart', 6)
            ->has('wasteChart', 6)
            ->has('notificationSummary')
            ->has('header')
            ->has('filters')
            ->has('availableMonths')
            ->has('availableOrganizations')
            ->has('stats.waste_total_records_snapshot')
            ->has('stats.waste_transported_records_snapshot')
            ->has('stats.waste_untransported_records_snapshot')
        );
});

test('dashboard reads movement-based faba data from migrated tenant schema', function () {
    $organization = Organization::factory()->create([
        'code' => 'DASHFABA',
        'schema_name' => 'tenant_dashboard_faba_ready',
    ]);

    migrateTenantSchema($organization);

    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->has('fabaChart', 6)
            ->has('fabaStats')
        );
});

test('dashboard waste category percentages are based on approved quantity in the selected month and sum to 100 percent', function () {
    $organization = Organization::factory()->create([
        'code' => 'DASHCAT',
        'schema_name' => 'tenant_dashboard_category',
    ]);

    $tenantService = app(TenantService::class);
    migrateTenantSchema($organization);
    $tenantService->switchToSchema($organization->schema_name);

    $categoryA = WasteCategory::factory()->create(['name' => 'B3']);
    $categoryB = WasteCategory::factory()->create(['name' => 'Daur Ulang']);
    $characteristic = WasteCharacteristic::factory()->create();
    $typeA = WasteType::factory()->create([
        'category_id' => $categoryA->id,
        'characteristic_id' => $characteristic->id,
    ]);
    $typeB = WasteType::factory()->create([
        'category_id' => $categoryB->id,
        'characteristic_id' => $characteristic->id,
    ]);

    WasteRecord::factory()->count(3)->create([
        'waste_type_id' => $typeA->id,
        'status' => 'approved',
        'quantity' => 10,
        'date' => '2026-03-05',
    ]);
    WasteRecord::factory()->create([
        'waste_type_id' => $typeB->id,
        'status' => 'approved',
        'quantity' => 20,
        'date' => '2026-03-06',
    ]);
    WasteRecord::factory()->count(2)->create([
        'waste_type_id' => $typeB->id,
        'status' => 'draft',
        'quantity' => 50,
        'date' => '2026-03-07',
    ]);

    $tenantService->switchToPublic();

    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', [
        'month' => '2026-03',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('wasteByCategory', function ($categories): bool {
                $categories = collect($categories)->map(function ($category): array {
                    return is_array($category) ? $category : $category->toArray();
                })->values();

                if ($categories->count() !== 2) {
                    return false;
                }

                $byCategory = $categories->keyBy('label');
                $percentageTotal = round((float) $categories->sum('percentage'), 2);

                return $byCategory->has('B3')
                    && $byCategory->has('Daur Ulang')
                    && round((float) $byCategory->get('B3')['value'], 2) === 30.0
                    && round((float) $byCategory->get('Daur Ulang')['value'], 2) === 20.0
                    && round((float) $byCategory->get('B3')['percentage'], 2) === 60.0
                    && round((float) $byCategory->get('Daur Ulang')['percentage'], 2) === 40.0
                    && $percentageTotal === 100.0;
            })
        );
});

test('dashboard exposes waste flow chart and faba production material distribution', function () {
    $organization = Organization::factory()->create([
        'code' => 'DASHFLOW',
        'schema_name' => 'tenant_dashboard_flow',
    ]);

    $tenantService = app(TenantService::class);
    migrateTenantSchema($organization);
    $tenantService->switchToSchema($organization->schema_name);

    $category = WasteCategory::factory()->create(['name' => 'B3']);
    $characteristic = WasteCharacteristic::factory()->create();
    $type = WasteType::factory()->create([
        'category_id' => $category->id,
        'characteristic_id' => $characteristic->id,
    ]);

    $marchRecord = WasteRecord::factory()->approved()->create([
        'waste_type_id' => $type->id,
        'date' => '2026-03-05',
    ]);
    WasteRecord::factory()->approved()->create([
        'waste_type_id' => $type->id,
        'date' => '2026-03-12',
    ]);
    WasteRecord::factory()->approved()->create([
        'waste_type_id' => $type->id,
        'date' => '2026-02-11',
    ]);

    $vendor = Vendor::factory()->create();

    WasteTransportation::query()->create([
        'waste_record_id' => $marchRecord->id,
        'vendor_id' => $vendor->id,
        'transportation_date' => '2026-03-20',
        'quantity' => 10,
        'unit' => 'kg',
        'vehicle_number' => 'B 1234 DASH',
        'driver_name' => 'Driver Dashboard',
        'driver_phone' => '0812000001',
        'status' => 'delivered',
        'dispatched_at' => now()->subDay(),
        'delivered_at' => now(),
    ]);
    WasteTransportation::query()->create([
        'waste_record_id' => $marchRecord->id,
        'vendor_id' => $vendor->id,
        'transportation_date' => '2026-02-18',
        'quantity' => 8,
        'unit' => 'kg',
        'vehicle_number' => 'B 1235 DASH',
        'driver_name' => 'Driver Dashboard 2',
        'driver_phone' => '0812000002',
        'status' => 'delivered',
        'dispatched_at' => now()->subDays(2),
        'delivered_at' => now()->subDay(),
    ]);

    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-03',
        'period_year' => 2026,
        'period_month' => 3,
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'quantity' => 30,
    ]);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-07',
        'period_year' => 2026,
        'period_month' => 3,
        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'quantity' => 10,
    ]);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-02-10',
        'period_year' => 2026,
        'period_month' => 2,
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_WORKSHOP,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'quantity' => 20,
    ]);

    $tenantService->switchToPublic();

    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('wasteChart', function ($chart): bool {
                $chart = collect($chart)->map(fn ($item): array => is_array($item) ? $item : $item->toArray())->values();
                $march = $chart->firstWhere('year', 2026);
                $march = $chart->first(fn (array $item): bool => $item['year'] === 2026 && $item['month'] === 3);
                $february = $chart->first(fn (array $item): bool => $item['year'] === 2026 && $item['month'] === 2);

                return $chart->count() === 6
                    && $march !== null
                    && $february !== null
                    && $march['input_count'] === 2
                    && $march['transported_count'] === 1
                    && $february['input_count'] === 1
                    && $february['transported_count'] === 1;
            })
            ->where('fabaProductionMaterialDistribution', function ($distribution): bool {
                $distribution = collect($distribution)->map(fn ($item): array => is_array($item) ? $item : $item->toArray())->keyBy('label');

                return $distribution->count() === 2
                    && round((float) $distribution->get('Fly Ash')['value'], 2) === 30.0
                    && round((float) $distribution->get('Bottom Ash')['value'], 2) === 10.0
                    && round((float) $distribution->get('Fly Ash')['percentage'], 2) === 75.0
                    && round((float) $distribution->get('Bottom Ash')['percentage'], 2) === 25.0;
            })
        );

    $tenantService->switchToPublic();
});

test('dashboard filters metrics by selected month', function () {
    $organization = Organization::factory()->create([
        'code' => 'DASHMONTH',
        'schema_name' => 'tenant_dashboard_month',
    ]);

    migrateTenantSchema($organization);
    $tenantService = app(TenantService::class);
    $tenantService->switchToSchema($organization->schema_name);

    $category = WasteCategory::factory()->create(['name' => 'B3']);
    $characteristic = WasteCharacteristic::factory()->create();
    $type = WasteType::factory()->create([
        'category_id' => $category->id,
        'characteristic_id' => $characteristic->id,
    ]);

    WasteRecord::factory()->approved()->create([
        'waste_type_id' => $type->id,
        'quantity' => 15,
        'date' => '2026-01-08',
    ]);
    WasteRecord::factory()->approved()->count(2)->create([
        'waste_type_id' => $type->id,
        'quantity' => 25,
        'date' => '2026-03-08',
    ]);

    FabaMovement::factory()->create([
        'transaction_date' => '2026-01-03',
        'period_year' => 2026,
        'period_month' => 1,
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'quantity' => 15,
    ]);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-03',
        'period_year' => 2026,
        'period_month' => 3,
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'quantity' => 40,
    ]);

    $tenantService->switchToPublic();

    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', [
        'month' => '2026-01',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.month', '2026-01')
            ->where('organizationName', $organization->name)
            ->where('stats.waste_total_records_snapshot', 1)
            ->where('stats.total_waste_records', 1)
            ->where('fabaStats.total_production', 15)
            ->where('wasteByCategory.0.value', 15)
            ->where('fabaChart.5.month', 1)
            ->where('fabaChart.5.year', 2026)
        );
});

test('dashboard exposes waste snapshot stats and untransported backlog up to the selected snapshot', function () {
    $organization = Organization::factory()->create([
        'code' => 'DASHSNAP',
        'schema_name' => 'tenant_dashboard_snapshot',
    ]);

    migrateTenantSchema($organization);
    $tenantService = app(TenantService::class);
    $tenantService->switchToSchema($organization->schema_name);

    $category = WasteCategory::factory()->create(['name' => 'B3']);
    $characteristic = WasteCharacteristic::factory()->create();
    $type = WasteType::factory()->create([
        'category_id' => $category->id,
        'characteristic_id' => $characteristic->id,
    ]);

    $januaryRecord = WasteRecord::factory()->approved()->create([
        'waste_type_id' => $type->id,
        'quantity' => 10,
        'unit' => 'kg',
        'date' => '2026-01-10',
    ]);

    $februaryRecord = WasteRecord::factory()->approved()->create([
        'waste_type_id' => $type->id,
        'quantity' => 20,
        'unit' => 'kg',
        'date' => '2026-02-14',
    ]);

    WasteRecord::factory()->pendingReview()->create([
        'waste_type_id' => $type->id,
        'quantity' => 15,
        'unit' => 'kg',
        'date' => '2026-03-18',
    ]);

    $vendor = Vendor::factory()->create();

    WasteTransportation::query()->create([
        'waste_record_id' => $januaryRecord->id,
        'vendor_id' => $vendor->id,
        'transportation_date' => '2026-01-18',
        'quantity' => 10,
        'unit' => 'kg',
        'vehicle_number' => 'B 4567 YTD',
        'driver_name' => 'Driver Januari',
        'driver_phone' => '0812000101',
        'status' => 'delivered',
        'dispatched_at' => now()->subDays(10),
        'delivered_at' => now()->subDays(9),
    ]);

    WasteTransportation::query()->create([
        'waste_record_id' => $februaryRecord->id,
        'vendor_id' => $vendor->id,
        'transportation_date' => '2026-02-20',
        'quantity' => 5,
        'unit' => 'kg',
        'vehicle_number' => 'B 4568 YTD',
        'driver_name' => 'Driver Februari',
        'driver_phone' => '0812000102',
        'status' => 'cancelled',
        'dispatched_at' => null,
        'delivered_at' => null,
    ]);

    $tenantService->switchToPublic();

    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', [
        'month' => '2026-03',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.waste_total_records_snapshot', 3)
            ->where('stats.waste_transported_records_snapshot', 1)
            ->where('stats.waste_untransported_records_snapshot', 1)
            ->where('stats.total_waste_records', 1)
        );
});

test('dashboard waste backlog carries over across years until transportation is complete', function () {
    $organization = Organization::factory()->create([
        'code' => 'DASHCARRY',
        'schema_name' => 'tenant_dashboard_carry',
    ]);

    migrateTenantSchema($organization);
    $tenantService = app(TenantService::class);
    $tenantService->switchToSchema($organization->schema_name);

    $category = WasteCategory::factory()->create(['name' => 'B3']);
    $characteristic = WasteCharacteristic::factory()->create();
    $type = WasteType::factory()->create([
        'category_id' => $category->id,
        'characteristic_id' => $characteristic->id,
    ]);

    $novemberRecord = WasteRecord::factory()->approved()->create([
        'waste_type_id' => $type->id,
        'quantity' => 10,
        'unit' => 'kg',
        'date' => '2025-11-12',
    ]);

    $decemberRecord = WasteRecord::factory()->approved()->create([
        'waste_type_id' => $type->id,
        'quantity' => 18,
        'unit' => 'kg',
        'date' => '2025-12-15',
    ]);

    WasteRecord::factory()->approved()->create([
        'waste_type_id' => $type->id,
        'quantity' => 12,
        'unit' => 'kg',
        'date' => '2026-01-20',
    ]);

    $vendor = Vendor::factory()->create();

    WasteTransportation::query()->create([
        'waste_record_id' => $novemberRecord->id,
        'vendor_id' => $vendor->id,
        'transportation_date' => '2025-11-20',
        'quantity' => 10,
        'unit' => 'kg',
        'vehicle_number' => 'B 5567 CAR',
        'driver_name' => 'Driver November',
        'driver_phone' => '0812000201',
        'status' => 'delivered',
        'dispatched_at' => now()->subDays(12),
        'delivered_at' => now()->subDays(11),
    ]);

    WasteTransportation::query()->create([
        'waste_record_id' => $decemberRecord->id,
        'vendor_id' => $vendor->id,
        'transportation_date' => '2025-12-22',
        'quantity' => 6,
        'unit' => 'kg',
        'vehicle_number' => 'B 5568 CAR',
        'driver_name' => 'Driver Desember',
        'driver_phone' => '0812000202',
        'status' => 'delivered',
        'dispatched_at' => now()->subDays(8),
        'delivered_at' => now()->subDays(7),
    ]);

    $tenantService->switchToPublic();

    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', [
        'month' => '2026-01',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('stats.waste_total_records_snapshot', 3)
            ->where('stats.waste_transported_records_snapshot', 2)
            ->where('stats.waste_untransported_records_snapshot', 2)
            ->where('stats.total_waste_records', 1)
        );
});

test('superadmin can switch dashboard organization and tenant schema', function () {
    $organizationA = Organization::factory()->create([
        'name' => 'Org Alpha',
        'code' => 'ORGALPHA',
        'schema_name' => 'tenant_dashboard_alpha',
    ]);
    $organizationB = Organization::factory()->create([
        'name' => 'Org Beta',
        'code' => 'ORGBETA',
        'schema_name' => 'tenant_dashboard_beta',
    ]);

    migrateTenantSchema($organizationA);
    migrateTenantSchema($organizationB);

    $tenantService = app(TenantService::class);

    $tenantService->switchToSchema($organizationA->schema_name);
    $categoryA = WasteCategory::factory()->create(['name' => 'B3']);
    $characteristicA = WasteCharacteristic::factory()->create();
    $typeA = WasteType::factory()->create([
        'category_id' => $categoryA->id,
        'characteristic_id' => $characteristicA->id,
    ]);
    WasteRecord::factory()->approved()->create([
        'waste_type_id' => $typeA->id,
        'date' => '2026-03-05',
    ]);

    $tenantService->switchToSchema($organizationB->schema_name);
    $categoryB = WasteCategory::factory()->create(['name' => 'Daur Ulang']);
    $characteristicB = WasteCharacteristic::factory()->create();
    $typeB = WasteType::factory()->create([
        'category_id' => $categoryB->id,
        'characteristic_id' => $characteristicB->id,
    ]);
    WasteRecord::factory()->approved()->count(3)->create([
        'waste_type_id' => $typeB->id,
        'date' => '2026-03-05',
    ]);
    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-12',
        'period_year' => 2026,
        'period_month' => 3,
        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'quantity' => 22,
    ]);
    $tenantService->switchToPublic();

    $superAdmin = User::factory()->create([
        'organization_id' => $organizationA->id,
        'is_super_admin' => true,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($superAdmin)->get(route('dashboard', [
        'organization_id' => $organizationB->id,
        'month' => '2026-03',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('organizationName', 'Org Beta')
            ->where('filters.organization_id', $organizationB->id)
            ->where('stats.total_waste_records', 3)
            ->where('fabaStats.total_production', 22)
            ->where('availableOrganizations', function ($organizations) use ($organizationA, $organizationB): bool {
                $organizations = collect($organizations)->map(fn ($item): array => is_array($item) ? $item : $item->toArray());

                return $organizations->pluck('id')->contains($organizationA->id)
                    && $organizations->pluck('id')->contains($organizationB->id);
            })
        );
});

test('dashboard exposes critical risk metadata when expired waste exists', function () {
    CarbonImmutable::setTestNow('2026-04-02 09:00:00');

    $organization = Organization::factory()->create([
        'code' => 'DASHRISKCRIT',
        'schema_name' => 'tenant_dashboard_risk_critical',
    ]);

    migrateTenantSchema($organization);
    $tenantService = app(TenantService::class);
    $tenantService->switchToSchema($organization->schema_name);

    $wasteType = createDashboardWasteType(1);

    WasteRecord::factory()->approved()->create([
        'waste_type_id' => $wasteType->id,
        'date' => '2026-04-01',
    ]);

    $tenantService->switchToPublic();

    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', [
        'month' => '2026-04',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('header.risk_status', 'critical')
            ->where('header.risk_tone', 'red')
            ->where('header.risk_label', 'Kritis')
            ->where('notificationSummary.expired_waste_count', 1)
            ->where('notificationSummary.faba_warnings_count', 0)
        );

    CarbonImmutable::setTestNow();
});

test('dashboard exposes warning risk metadata when waste is expiring soon', function () {
    CarbonImmutable::setTestNow('2026-04-02 09:00:00');

    $organization = Organization::factory()->create([
        'code' => 'DASHRISKWARN',
        'schema_name' => 'tenant_dashboard_risk_warning',
    ]);

    migrateTenantSchema($organization);
    $tenantService = app(TenantService::class);
    $tenantService->switchToSchema($organization->schema_name);

    $wasteType = createDashboardWasteType(4);

    WasteRecord::factory()->approved()->create([
        'waste_type_id' => $wasteType->id,
        'date' => '2026-04-01',
    ]);

    $tenantService->switchToPublic();

    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', [
        'month' => '2026-04',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('header.risk_status', 'warning')
            ->where('header.risk_tone', 'orange')
            ->where('header.risk_label', 'Perlu Perhatian')
            ->where('notificationSummary.expired_waste_count', 0)
            ->where('notificationSummary.expiring_soon_waste_count', 1)
            ->where('notificationSummary.faba_warnings_count', 0)
        );

    CarbonImmutable::setTestNow();
});

test('dashboard exposes normal risk metadata when no active compliance issues exist', function () {
    CarbonImmutable::setTestNow('2026-04-02 09:00:00');

    $organization = Organization::factory()->create([
        'code' => 'DASHRISKNORM',
        'schema_name' => 'tenant_dashboard_risk_normal',
    ]);

    migrateTenantSchema($organization);
    $tenantService = app(TenantService::class);
    $tenantService->switchToSchema($organization->schema_name);

    $wasteType = createDashboardWasteType(20);

    WasteRecord::factory()->approved()->create([
        'waste_type_id' => $wasteType->id,
        'date' => '2026-04-01',
    ]);

    $tenantService->switchToPublic();

    $user = User::factory()->create([
        'organization_id' => $organization->id,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard', [
        'month' => '2026-04',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('header.risk_status', 'normal')
            ->where('header.risk_tone', 'green')
            ->where('header.risk_label', 'Normal')
            ->where('notificationSummary.expired_waste_count', 0)
            ->where('notificationSummary.expiring_soon_waste_count', 0)
            ->where('notificationSummary.faba_warnings_count', 0)
        );

    CarbonImmutable::setTestNow();
});
