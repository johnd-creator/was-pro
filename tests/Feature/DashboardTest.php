<?php

use App\Models\FabaMonthlyApproval;
use App\Models\FabaMovement;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WasteCategory;
use App\Models\WasteCharacteristic;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
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
            ->has('tasks')
            ->has('taskContext')
            ->has('wasteHaulingStatusDistribution')
            ->has('wasteBacklogUrgencyDistribution')
            ->has('fabaStats')
            ->has('fabaHeroStats')
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

test('dashboard demo seed shows april as critical and previous month as safe', function () {
    CarbonImmutable::setTestNow('2026-04-16 10:00:00');

    $tenantCode = 'DASHDEMO';
    $schemaName = 'tenant_dashboard_demo';

    $this->artisan('waste-management:seed-demo', [
        '--tenant' => $tenantCode,
        '--schema' => $schemaName,
        '--fresh-tenant' => true,
    ])->assertSuccessful();

    $organization = Organization::query()->where('code', $tenantCode)->firstOrFail();
    $supervisor = User::query()->where('email', 'wm.supervisor.demo@local.test')
        ->where('organization_id', $organization->id)
        ->firstOrFail();

    $criticalResponse = $this->actingAs($supervisor)
        ->get(route('dashboard', ['month' => '2026-04']));

    $criticalResponse
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('header.risk_status', 'critical')
            ->where('stats.expired_waste', 2)
            ->where('haulingAttentionCount', 4)
        );

    $safeResponse = $this->actingAs($supervisor)
        ->get(route('dashboard', ['month' => '2026-03']));

    $safeResponse
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('header.risk_status', 'normal')
            ->where('stats.expired_waste', 0)
        );

    CarbonImmutable::setTestNow();
});

test('dashboard shows approval tasks for supervisor users', function () {
    $organization = Organization::factory()->create([
        'code' => 'DASHSUP',
        'schema_name' => 'tenant_dashboard_supervisor_tasks',
    ]);
    migrateTenantSchema($organization);

    $submitter = User::factory()->create([
        'organization_id' => $organization->id,
        'email_verified_at' => now(),
    ]);

    $approvePermission = Permission::query()->firstOrCreate(
        ['slug' => 'waste_records.approve'],
        [
            'name' => 'Approve Waste Records',
            'module' => 'waste_records',
            'description' => 'Approve waste records',
            'is_active' => true,
        ],
    );

    $supervisorRole = Role::query()->firstOrCreate(
        ['slug' => 'supervisor'],
        [
            'name' => 'Supervisor',
            'description' => 'Supervisor role',
            'level' => 50,
            'is_active' => true,
        ],
    );
    $supervisorRole->permissions()->syncWithoutDetaching([$approvePermission->id]);

    $supervisor = User::factory()->create([
        'organization_id' => $organization->id,
        'role_id' => $supervisorRole->id,
        'email_verified_at' => now(),
    ]);

    $tenantService = app(TenantService::class);
    $tenantService->switchToSchema($organization->schema_name);

    $wasteType = createDashboardWasteType(90);

    $wasteRecord = WasteRecord::factory()->create([
        'waste_type_id' => $wasteType->id,
        'status' => 'pending_review',
        'date' => '2026-04-08',
        'created_by' => $submitter->id,
        'submitted_by' => $submitter->id,
        'submitted_at' => now()->subDays(2),
    ]);

    $fabaApproval = FabaMonthlyApproval::factory()->create([
        'year' => 2026,
        'month' => 4,
        'status' => FabaMonthlyApproval::STATUS_SUBMITTED,
        'submitted_by' => $submitter->id,
        'submitted_at' => now()->subDay(),
    ]);

    $tenantService->switchToPublic();

    $response = $this->actingAs($supervisor)->get(route('dashboard', [
        'month' => '2026-04',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('taskContext', 'approver')
            ->where('tasks', function ($tasks) use ($wasteRecord, $fabaApproval): bool {
                $taskIds = collect($tasks)->pluck('id');
                $taskGroups = collect($tasks)->pluck('task_group');

                return $taskIds->contains($wasteRecord->id)
                    && $taskIds->contains($fabaApproval->id)
                    && $taskGroups->every(fn (string $group): bool => $group === 'approval');
            })
        );
});

test('dashboard shows only operator follow-up tasks for their own records', function () {
    $organization = Organization::factory()->create([
        'code' => 'DASHOPR',
        'schema_name' => 'tenant_dashboard_operator_tasks',
    ]);
    migrateTenantSchema($organization);

    $operatorRole = Role::query()->firstOrCreate(
        ['slug' => 'operator'],
        [
            'name' => 'Operator',
            'description' => 'Operator role',
            'level' => 20,
            'is_active' => true,
        ],
    );

    $operator = User::factory()->create([
        'organization_id' => $organization->id,
        'role_id' => $operatorRole->id,
        'email_verified_at' => now(),
    ]);

    $otherOperator = User::factory()->create([
        'organization_id' => $organization->id,
        'role_id' => $operatorRole->id,
        'email_verified_at' => now(),
    ]);

    $tenantService = app(TenantService::class);
    $tenantService->switchToSchema($organization->schema_name);

    $wasteType = createDashboardWasteType(90);

    $rejectedWasteRecord = WasteRecord::factory()->create([
        'waste_type_id' => $wasteType->id,
        'status' => 'rejected',
        'date' => '2026-04-06',
        'created_by' => $operator->id,
        'submitted_by' => $operator->id,
        'submitted_at' => now()->subDays(3),
    ]);

    WasteRecord::factory()->create([
        'waste_type_id' => $wasteType->id,
        'status' => 'rejected',
        'date' => '2026-04-07',
        'created_by' => $otherOperator->id,
        'submitted_by' => $otherOperator->id,
        'submitted_at' => now()->subDays(2),
    ]);

    $rejectedFabaApproval = FabaMonthlyApproval::factory()->create([
        'year' => 2026,
        'month' => 4,
        'status' => FabaMonthlyApproval::STATUS_REJECTED,
        'submitted_by' => $operator->id,
        'submitted_at' => now()->subDays(2),
        'rejected_by' => $otherOperator->id,
        'rejected_at' => now()->subDay(),
    ]);

    $tenantService->switchToPublic();

    $response = $this->actingAs($operator)->get(route('dashboard', [
        'month' => '2026-04',
    ]));

    $response
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->where('taskContext', 'operator')
            ->where('tasks', function ($tasks) use ($rejectedWasteRecord, $rejectedFabaApproval): bool {
                $taskIds = collect($tasks)->pluck('id');
                $taskGroups = collect($tasks)->pluck('task_group');

                return $taskIds->contains($rejectedWasteRecord->id)
                    && $taskIds->contains($rejectedFabaApproval->id)
                    && $taskIds->count() === 2
                    && $taskGroups->every(fn (string $group): bool => in_array($group, ['revision', 'follow_up'], true));
            })
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
            ->has('fabaHeroStats')
        );
});

test('dashboard exposes faba hero stats using yearly semantics', function () {
    $organization = Organization::factory()->create([
        'code' => 'DASHFABAHERO',
        'schema_name' => 'tenant_dashboard_faba_hero',
    ]);

    migrateTenantSchema($organization);

    $tenantService = app(TenantService::class);
    $tenantService->switchToSchema($organization->schema_name);

    FabaMovement::factory()->create([
        'transaction_date' => '2026-01-10',
        'period_year' => 2026,
        'period_month' => 1,
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'approval_status' => FabaMovement::STATUS_APPROVED,
        'quantity' => 10,
    ]);

    FabaMovement::factory()->create([
        'transaction_date' => '2026-02-08',
        'period_year' => 2026,
        'period_month' => 2,
        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
        'movement_type' => FabaMovement::TYPE_PRODUCTION,
        'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
        'approval_status' => FabaMovement::STATUS_APPROVED,
        'quantity' => 15,
    ]);

    FabaMovement::factory()->create([
        'transaction_date' => '2026-02-14',
        'period_year' => 2026,
        'period_month' => 2,
        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
        'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL,
        'stock_effect' => FabaMovement::STOCK_EFFECT_OUT,
        'approval_status' => FabaMovement::STATUS_APPROVED,
        'quantity' => 4,
    ]);

    FabaMovement::factory()->create([
        'transaction_date' => '2026-03-11',
        'period_year' => 2026,
        'period_month' => 3,
        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
        'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL,
        'stock_effect' => FabaMovement::STOCK_EFFECT_OUT,
        'approval_status' => FabaMovement::STATUS_APPROVED,
        'quantity' => 6,
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
            ->component('Dashboard')
            ->where('fabaStats.total_production', 0)
            ->where('fabaHeroStats.year', 2026)
            ->where('fabaHeroStats.total_production', 25)
            ->where('fabaHeroStats.total_utilization', 10)
            ->where('fabaHeroStats.current_balance', 15)
        );
});

test('dashboard hauling status distribution reflects approved snapshot records', function () {
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

    $notHauled = WasteRecord::factory()->create([
        'waste_type_id' => $typeA->id,
        'status' => 'approved',
        'quantity' => 10,
        'date' => '2026-03-05',
    ]);
    $partialRecord = WasteRecord::factory()->create([
        'waste_type_id' => $typeB->id,
        'status' => 'approved',
        'quantity' => 20,
        'date' => '2026-03-06',
    ]);
    $pendingRecord = WasteRecord::factory()->create([
        'waste_type_id' => $typeB->id,
        'status' => 'approved',
        'quantity' => 12,
        'date' => '2026-03-07',
    ]);
    $completedRecord = WasteRecord::factory()->create([
        'waste_type_id' => $typeA->id,
        'status' => 'approved',
        'quantity' => 8,
        'date' => '2026-03-08',
    ]);

    WasteHauling::factory()->create([
        'waste_record_id' => $partialRecord->id,
        'hauling_date' => '2026-03-12',
        'quantity' => 5,
        'unit' => 'kg',
        'status' => 'approved',
    ]);
    WasteHauling::factory()->create([
        'waste_record_id' => $pendingRecord->id,
        'hauling_date' => '2026-03-13',
        'quantity' => 6,
        'unit' => 'kg',
        'status' => 'pending_approval',
    ]);
    WasteHauling::factory()->create([
        'waste_record_id' => $completedRecord->id,
        'hauling_date' => '2026-03-14',
        'quantity' => 8,
        'unit' => 'kg',
        'status' => 'approved',
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
            ->where('wasteHaulingStatusDistribution', function ($distribution): bool {
                $distribution = collect($distribution)
                    ->map(fn ($item): array => is_array($item) ? $item : $item->toArray())
                    ->keyBy('label');

                return $distribution->count() === 4
                    && $distribution->get('Belum Diangkut')['value'] === 1
                    && $distribution->get('Sebagian Diangkut')['value'] === 1
                    && $distribution->get('Menunggu Persetujuan')['value'] === 1
                    && $distribution->get('Selesai')['value'] === 1;
            })
        );
});

test('dashboard exposes waste flow chart and backlog urgency distribution', function () {
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
        'storage_period_days' => 90,
    ]);
    $expiredType = WasteType::factory()->create([
        'category_id' => $category->id,
        'characteristic_id' => $characteristic->id,
        'storage_period_days' => 5,
    ]);

    $februaryRecord = WasteRecord::factory()->approved()->create([
        'waste_type_id' => $type->id,
        'date' => '2026-02-11',
        'quantity' => 10,
        'unit' => 'kg',
    ]);
    $marchCompletedRecord = WasteRecord::factory()->approved()->create([
        'waste_type_id' => $type->id,
        'date' => '2026-03-05',
        'quantity' => 10,
        'unit' => 'kg',
    ]);
    $marchBacklogRecord = WasteRecord::factory()->approved()->create([
        'waste_type_id' => $type->id,
        'date' => '2026-03-12',
        'quantity' => 12,
        'unit' => 'kg',
    ]);
    $expiredBacklogRecord = WasteRecord::factory()->approved()->create([
        'waste_type_id' => $expiredType->id,
        'date' => '2026-03-20',
        'quantity' => 8,
        'unit' => 'kg',
    ]);
    $expiringSoonType = createDashboardWasteType(15);
    $expiringSoonBacklogRecord = WasteRecord::factory()->approved()->create([
        'waste_type_id' => $expiringSoonType->id,
        'date' => '2026-03-22',
        'quantity' => 5,
        'unit' => 'kg',
    ]);

    WasteHauling::factory()->create([
        'waste_record_id' => $februaryRecord->id,
        'hauling_date' => '2026-02-18',
        'quantity' => 10,
        'unit' => 'kg',
        'status' => 'approved',
    ]);
    WasteHauling::factory()->create([
        'waste_record_id' => $marchCompletedRecord->id,
        'hauling_date' => '2026-03-20',
        'quantity' => 10,
        'unit' => 'kg',
        'status' => 'approved',
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
                    && $march['approved_input_count'] === 4
                    && $march['completed_count'] === 1
                    && $march['closing_backlog_count'] === 3
                    && $february['approved_input_count'] === 1
                    && $february['completed_count'] === 1
                    && $february['closing_backlog_count'] === 0;
            })
            ->where('wasteBacklogUrgencyDistribution', function ($distribution): bool {
                $distribution = collect($distribution)->map(fn ($item): array => is_array($item) ? $item : $item->toArray())->keyBy('label');

                return $distribution->count() === 3
                    && $distribution->get('Expired')['value'] === 1
                    && $distribution->get('Mendekati Batas Simpan')['value'] === 1
                    && $distribution->get('Masih Aman')['value'] === 1;
            })
            ->where('fabaChart', function ($chart): bool {
                $chart = collect($chart)->map(fn ($item): array => is_array($item) ? $item : $item->toArray());
                $march = $chart->first(fn (array $item): bool => $item['year'] === 2026 && $item['month'] === 3);

                return $march !== null
                    && array_key_exists('has_warning', $march)
                    && array_key_exists('warning_count', $march)
                    && is_bool($march['has_warning'])
                    && is_int($march['warning_count']);
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
            ->where('wasteChart.5.approved_input_count', 1)
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

    WasteHauling::factory()->create([
        'waste_record_id' => $januaryRecord->id,
        'hauling_date' => '2026-01-18',
        'quantity' => 10,
        'unit' => 'kg',
        'status' => 'approved',
    ]);

    WasteHauling::factory()->create([
        'waste_record_id' => $februaryRecord->id,
        'hauling_date' => '2026-02-20',
        'quantity' => 5,
        'unit' => 'kg',
        'status' => 'cancelled',
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

    WasteHauling::factory()->create([
        'waste_record_id' => $novemberRecord->id,
        'hauling_date' => '2025-11-20',
        'quantity' => 10,
        'unit' => 'kg',
        'status' => 'approved',
    ]);

    WasteHauling::factory()->create([
        'waste_record_id' => $decemberRecord->id,
        'hauling_date' => '2025-12-22',
        'quantity' => 6,
        'unit' => 'kg',
        'status' => 'approved',
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
