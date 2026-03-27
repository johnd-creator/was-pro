<?php

namespace App\Services;

use App\Models\FabaAuditLog;
use App\Models\FabaInternalDestination;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaMonthlyClosingSnapshot;
use App\Models\FabaMovement;
use App\Models\FabaOpeningBalance;
use App\Models\FabaPurpose;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use RuntimeException;

class FabaDemoDataService
{
    public const DEFAULT_TENANT_CODE = 'TWMSDEMO';

    public const DEFAULT_SCHEMA_NAME = 'tenant_twms_demo';

    public function __construct(
        protected TenantService $tenantService,
        protected FabaAuditService $fabaAuditService,
        protected FabaRecapService $fabaRecapService,
    ) {}

    /**
     * @return array{
     *     organization: \App\Models\Organization,
     *     schema_name: string,
     *     periods: list<string>,
     *     vendors_count: int,
     *     internal_destinations_count: int,
     *     purposes_count: int,
     *     movements_count: int,
     *     approvals_count: int,
     *     opening_balances_count: int,
     *     snapshots_count: int
     * }
     */
    public function seedDemoData(
        ?string $tenantCode = null,
        ?string $schemaName = null,
        bool $freshTenant = false,
    ): array {
        $tenantCode = Str::upper($tenantCode ?: self::DEFAULT_TENANT_CODE);
        $schemaName = $schemaName ?: $this->deriveSchemaName($tenantCode);

        $this->seedRolesAndPermissions();

        $existingOrganization = Organization::withTrashed()->where('code', $tenantCode)->first();
        $this->guardFreshTenantUsage($existingOrganization, $freshTenant, $tenantCode);

        $organization = $this->upsertOrganization($tenantCode, $schemaName);

        if ($freshTenant && $this->tenantService->schemaExists($schemaName)) {
            $this->tenantService->dropSchema($schemaName);
        }

        if (! $this->tenantService->schemaExists($schemaName)) {
            $this->tenantService->createSchema($schemaName);
        }

        $this->tenantService->runMigrationsForTenant($schemaName, 'database/migrations/tenant');

        $users = $this->upsertUsers($organization);
        $periods = $this->getSimulationPeriods();

        try {
            $this->tenantService->switchToSchema($schemaName);

            if ($this->datasetAlreadyExists($periods)) {
                $this->purgeExistingDataset($periods);
            }

            $vendors = $this->createVendors($users['supervisor']);
            $internalDestinations = $this->createInternalDestinations();
            $purposes = $this->createPurposes();

            $summary = $this->seedDataset(
                $periods,
                $users['operator'],
                $users['supervisor'],
                $vendors,
                $internalDestinations,
                $purposes,
            );
        } finally {
            $this->tenantService->switchToPublic();
        }

        return [
            'organization' => $organization,
            'schema_name' => $schemaName,
            'periods' => array_map(
                fn (CarbonImmutable $period): string => $period->format('Y-m'),
                $periods,
            ),
            'vendors_count' => count($vendors),
            'internal_destinations_count' => count($internalDestinations),
            'purposes_count' => count($purposes),
            ...$summary,
        ];
    }

    protected function seedRolesAndPermissions(): void
    {
        $originalSchema = $this->tenantService->getCurrentSchema();

        try {
            $this->tenantService->switchToPublic();

            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesSeeder', '--no-interaction' => true]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionsSeeder', '--no-interaction' => true]);
            Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionsSeeder', '--no-interaction' => true]);
        } finally {
            if ($originalSchema && $originalSchema !== 'public') {
                $this->tenantService->switchToSchema($originalSchema);
            } else {
                $this->tenantService->switchToPublic();
            }
        }
    }

    protected function deriveSchemaName(string $tenantCode): string
    {
        if ($tenantCode === self::DEFAULT_TENANT_CODE) {
            return self::DEFAULT_SCHEMA_NAME;
        }

        return 'tenant_'.Str::of($tenantCode)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');
    }

    protected function upsertOrganization(string $tenantCode, string $schemaName): Organization
    {
        $organization = Organization::withTrashed()->where('code', $tenantCode)->first();

        if ($organization) {
            if ($organization->trashed()) {
                $organization->restore();
            }

            if ($tenantCode !== self::DEFAULT_TENANT_CODE) {
                return $organization->fresh();
            }

            $organization->update([
                'name' => 'FABA Demo',
                'schema_name' => $schemaName,
                'description' => 'Tenant demo untuk simulasi modul FABA berbasis movement ledger.',
                'address' => 'Area Demo FABA',
                'phone' => '0210000000',
                'email' => 'faba.demo@local.test',
                'is_active' => true,
            ]);

            return $organization->fresh();
        }

        return Organization::query()->create([
            'name' => 'FABA Demo',
            'code' => $tenantCode,
            'schema_name' => $schemaName,
            'description' => 'Tenant demo untuk simulasi modul FABA berbasis movement ledger.',
            'address' => 'Area Demo FABA',
            'phone' => '0210000000',
            'email' => 'faba.demo@local.test',
            'is_active' => true,
        ]);
    }

    /**
     * @return array{operator: \App\Models\User, supervisor: \App\Models\User}
     */
    protected function upsertUsers(Organization $organization): array
    {
        $supervisorRoleId = Role::query()->where('slug', 'supervisor')->value('id');
        $operatorRoleId = Role::query()->where('slug', 'operator')->value('id');

        if (! $supervisorRoleId || ! $operatorRoleId) {
            throw new RuntimeException('Role supervisor/operator belum tersedia. Jalankan seed role terlebih dahulu.');
        }

        $supervisor = User::query()->updateOrCreate(
            ['email' => 'faba.supervisor.demo@local.test'],
            [
                'name' => 'Supervisor FABA Demo',
                'password' => 'password',
                'organization_id' => $organization->id,
                'role_id' => $supervisorRoleId,
                'email_verified_at' => now(),
                'is_super_admin' => false,
            ]
        );

        $operator = User::query()->updateOrCreate(
            ['email' => 'faba.operator.demo@local.test'],
            [
                'name' => 'Operator FABA Demo',
                'password' => 'password',
                'organization_id' => $organization->id,
                'role_id' => $operatorRoleId,
                'email_verified_at' => now(),
                'is_super_admin' => false,
            ]
        );

        return [
            'operator' => $operator,
            'supervisor' => $supervisor,
        ];
    }

    protected function guardFreshTenantUsage(?Organization $organization, bool $freshTenant, string $tenantCode): void
    {
        if (! $freshTenant || ! $organization) {
            return;
        }

        if ($tenantCode !== self::DEFAULT_TENANT_CODE) {
            throw new RuntimeException('Mode --fresh-tenant tidak diizinkan untuk tenant existing. Gunakan tenant demo terpisah atau seed tanpa overwrite.');
        }
    }

    /**
     * @return list<CarbonImmutable>
     */
    protected function getSimulationPeriods(): array
    {
        $startOfCurrentMonth = CarbonImmutable::now()->startOfMonth();

        return [
            $startOfCurrentMonth->subMonthsNoOverflow(3),
            $startOfCurrentMonth->subMonthsNoOverflow(2),
            $startOfCurrentMonth->subMonthsNoOverflow(1),
        ];
    }

    /**
     * @param  list<CarbonImmutable>  $periods
     */
    protected function datasetAlreadyExists(array $periods): bool
    {
        foreach ($periods as $period) {
            $year = (int) $period->format('Y');
            $month = (int) $period->format('n');

            if (
                FabaMovement::query()->forPeriod($year, $month)->exists()
                || FabaMonthlyApproval::query()->forPeriod($year, $month)->exists()
                || FabaOpeningBalance::query()->forPeriod($year, $month)->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<CarbonImmutable>  $periods
     */
    protected function purgeExistingDataset(array $periods): void
    {
        foreach ($periods as $period) {
            $year = (int) $period->format('Y');
            $month = (int) $period->format('n');

            FabaMonthlyClosingSnapshot::query()->forPeriod($year, $month)->delete();
            FabaMonthlyApproval::query()->forPeriod($year, $month)->delete();
            FabaOpeningBalance::query()->forPeriod($year, $month)->delete();
            FabaMovement::query()->forPeriod($year, $month)->delete();
            FabaAuditLog::query()->forPeriod($year, $month)->delete();
        }
    }

    /**
     * @return array<string, \App\Models\Vendor>
     */
    protected function createVendors(User $supervisor): array
    {
        return [
            'semen' => Vendor::query()->updateOrCreate(
                ['code' => 'FABA-VEND-001'],
                [
                    'name' => 'PT Semen Nusantara',
                    'description' => 'Mitra pemanfaatan FABA untuk industri semen.',
                    'contact_person' => 'Rina Pratiwi',
                    'phone' => '0811111111',
                    'email' => 'vendor.semen@local.test',
                    'address' => 'Kawasan Industri Semen',
                    'license_number' => 'LIC-FABA-001',
                    'license_expiry_date' => '2027-12-31',
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
            'konstruksi' => Vendor::query()->updateOrCreate(
                ['code' => 'FABA-VEND-002'],
                [
                    'name' => 'PT Konstruksi Hijau',
                    'description' => 'Mitra pemanfaatan FABA untuk konstruksi.',
                    'contact_person' => 'Dwi Lestari',
                    'phone' => '0822222222',
                    'email' => 'vendor.konstruksi@local.test',
                    'address' => 'Area Konstruksi Hijau',
                    'license_number' => 'LIC-FABA-002',
                    'license_expiry_date' => '2027-12-31',
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
            'paving' => Vendor::query()->updateOrCreate(
                ['code' => 'FABA-VEND-003'],
                [
                    'name' => 'CV Paving Sentosa',
                    'description' => 'Mitra pemanfaatan FABA untuk paving block.',
                    'contact_person' => 'Arif Nugroho',
                    'phone' => '0833333333',
                    'email' => 'vendor.paving@local.test',
                    'address' => 'Sentra Paving',
                    'license_number' => 'LIC-FABA-003',
                    'license_expiry_date' => '2027-12-31',
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
        ];
    }

    /**
     * @return array<string, \App\Models\FabaInternalDestination>
     */
    protected function createInternalDestinations(): array
    {
        return [
            'workshop' => FabaInternalDestination::query()->updateOrCreate(
                ['slug' => 'workshop-internal'],
                ['name' => 'Workshop Internal', 'description' => 'Pemanfaatan untuk kebutuhan workshop.', 'is_active' => true],
            ),
            'roadbase' => FabaInternalDestination::query()->updateOrCreate(
                ['slug' => 'roadbase-internal'],
                ['name' => 'Roadbase Internal', 'description' => 'Pemanfaatan untuk perbaikan jalan area operasi.', 'is_active' => true],
            ),
        ];
    }

    /**
     * @return array<string, \App\Models\FabaPurpose>
     */
    protected function createPurposes(): array
    {
        return [
            'cement' => FabaPurpose::query()->updateOrCreate(
                ['slug' => 'campuran-semen'],
                ['name' => 'Campuran Semen', 'description' => 'Kebutuhan bahan baku semen.', 'is_active' => true],
            ),
            'paving' => FabaPurpose::query()->updateOrCreate(
                ['slug' => 'paving-block'],
                ['name' => 'Paving Block', 'description' => 'Produksi paving block.', 'is_active' => true],
            ),
            'internal_fill' => FabaPurpose::query()->updateOrCreate(
                ['slug' => 'urugan-internal'],
                ['name' => 'Urugan Internal', 'description' => 'Kebutuhan penimbunan dan perbaikan area.', 'is_active' => true],
            ),
        ];
    }

    /**
     * @param  list<CarbonImmutable>  $periods
     * @param  array<string, \App\Models\Vendor>  $vendors
     * @param  array<string, \App\Models\FabaInternalDestination>  $internalDestinations
     * @param  array<string, \App\Models\FabaPurpose>  $purposes
     * @return array{
     *     movements_count: int,
     *     approvals_count: int,
     *     opening_balances_count: int,
     *     snapshots_count: int
     * }
     */
    protected function seedDataset(
        array $periods,
        User $operator,
        User $supervisor,
        array $vendors,
        array $internalDestinations,
        array $purposes,
    ): array {
        $openingBalancesCount = 0;
        $movementsCount = 0;
        $approvalsCount = 0;
        $snapshotsCount = 0;

        foreach ($periods as $index => $period) {
            $dataset = $this->periodDataset($period->format('Y-m'));
            $year = (int) $period->format('Y');
            $month = (int) $period->format('n');

            foreach ($dataset['opening_balances'] as $openingBalance) {
                $balance = FabaOpeningBalance::query()->create([
                    'year' => $year,
                    'month' => $month,
                    'material_type' => $openingBalance['material_type'],
                    'quantity' => $openingBalance['quantity'],
                    'note' => $openingBalance['note'],
                    'set_by' => $supervisor->id,
                    'set_at' => $period->setDay(1)->setTime(8, 0),
                ]);

                $this->fabaAuditService->log(
                    $supervisor->id,
                    'set_opening_balance',
                    FabaAuditLog::MODULE_BALANCE,
                    FabaOpeningBalance::class,
                    $balance->id,
                    $year,
                    $month,
                    'Opening balance demo disiapkan.',
                    [
                        'material_type' => $balance->material_type,
                        'quantity' => (float) $balance->quantity,
                    ]
                );

                $openingBalancesCount++;
            }

            foreach ($dataset['movements'] as $movementIndex => $movement) {
                $vendor = $movement['vendor'] ? $vendors[$movement['vendor']] : null;
                $internalDestination = $movement['internal_destination'] ? $internalDestinations[$movement['internal_destination']] : null;
                $purpose = $movement['purpose'] ? $purposes[$movement['purpose']] : null;
                $transactionDate = $period->setDay($movement['day'])->toDateString();

                $createdMovement = FabaMovement::query()->create([
                    'transaction_date' => $transactionDate,
                    'material_type' => $movement['material_type'],
                    'movement_type' => $movement['movement_type'],
                    'stock_effect' => $this->stockEffectForMovement($movement['movement_type']),
                    'quantity' => $movement['quantity'],
                    'unit' => FabaMovement::DEFAULT_UNIT,
                    'vendor_id' => $vendor?->id,
                    'internal_destination_id' => $internalDestination?->id,
                    'purpose_id' => $purpose?->id,
                    'document_number' => $movement['document_number'],
                    'document_date' => $movement['document_number']
                        ? $period->setDay($movement['document_day'] ?? $movement['day'])->toDateString()
                        : null,
                    'attachment_path' => null,
                    'reference_type' => null,
                    'reference_id' => null,
                    'period_year' => $year,
                    'period_month' => $month,
                    'created_by' => $operator->id,
                    'updated_by' => $operator->id,
                    'note' => $movement['note'],
                ]);

                $this->fabaAuditService->log(
                    $operator->id,
                    'create',
                    $this->auditModuleForMovement($movement['movement_type']),
                    FabaMovement::class,
                    $createdMovement->id,
                    $year,
                    $month,
                    'Movement demo dibuat.',
                    [
                        'display_number' => $this->makeMovementNumber($movement['movement_type'], $period, $movementIndex + 1),
                        'movement_type' => $createdMovement->movement_type,
                        'material_type' => $createdMovement->material_type,
                        'quantity' => (float) $createdMovement->quantity,
                    ]
                );

                $movementsCount++;
            }

            $approval = FabaMonthlyApproval::query()->create([
                'year' => $year,
                'month' => $month,
                'status' => $dataset['approval']['status'],
                'submitted_by' => $operator->id,
                'submitted_at' => $period->endOfMonth()->setTime(15, 0),
                'approved_by' => $dataset['approval']['status'] === FabaMonthlyApproval::STATUS_APPROVED ? $supervisor->id : null,
                'approved_at' => $dataset['approval']['status'] === FabaMonthlyApproval::STATUS_APPROVED ? $period->endOfMonth()->setTime(17, 30) : null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_note' => null,
            ]);

            $this->fabaAuditService->log(
                $operator->id,
                'submit',
                FabaAuditLog::MODULE_APPROVAL,
                FabaMonthlyApproval::class,
                $approval->id,
                $year,
                $month,
                'Periode demo diajukan untuk approval.',
                ['status' => $approval->status]
            );

            if ($approval->status === FabaMonthlyApproval::STATUS_APPROVED) {
                $snapshot = $this->fabaRecapService->storeMonthlyClosingSnapshot($year, $month, $supervisor->id);

                $this->fabaAuditService->log(
                    $supervisor->id,
                    'approve',
                    FabaAuditLog::MODULE_APPROVAL,
                    FabaMonthlyApproval::class,
                    $approval->id,
                    $year,
                    $month,
                    'Periode demo disetujui.',
                    ['status' => $approval->status]
                );

                if ($snapshot) {
                    $snapshotsCount++;
                }
            }

            if ($index === array_key_last($periods)) {
                $this->fabaAuditService->log(
                    $supervisor->id,
                    'review_note',
                    FabaAuditLog::MODULE_APPROVAL,
                    FabaMonthlyApproval::class,
                    $approval->id,
                    $year,
                    $month,
                    'Supervisor menandai periode demo untuk review operasional aktif.',
                    ['status' => $approval->status]
                );
            }

            $approvalsCount++;
        }

        return [
            'movements_count' => $movementsCount,
            'approvals_count' => $approvalsCount,
            'opening_balances_count' => $openingBalancesCount,
            'snapshots_count' => $snapshotsCount,
        ];
    }

    protected function stockEffectForMovement(string $movementType): string
    {
        return in_array($movementType, [
            FabaMovement::TYPE_OPENING_BALANCE,
            FabaMovement::TYPE_PRODUCTION,
            FabaMovement::TYPE_WORKSHOP,
            FabaMovement::TYPE_ADJUSTMENT_IN,
        ], true) ? FabaMovement::STOCK_EFFECT_IN : FabaMovement::STOCK_EFFECT_OUT;
    }

    protected function auditModuleForMovement(string $movementType): string
    {
        return in_array($movementType, [
            FabaMovement::TYPE_UTILIZATION_EXTERNAL,
            FabaMovement::TYPE_UTILIZATION_INTERNAL,
        ], true) ? FabaAuditLog::MODULE_UTILIZATION : FabaAuditLog::MODULE_PRODUCTION;
    }

    protected function makeMovementNumber(string $movementType, CarbonImmutable $period, int $sequence): string
    {
        $prefix = match ($movementType) {
            FabaMovement::TYPE_UTILIZATION_EXTERNAL => 'FUE',
            FabaMovement::TYPE_UTILIZATION_INTERNAL => 'FUI',
            FabaMovement::TYPE_WORKSHOP => 'FWK',
            FabaMovement::TYPE_REJECT => 'FRJ',
            FabaMovement::TYPE_DISPOSAL_POK => 'FPK',
            default => 'FPR',
        };

        return $prefix.'-'.$period->format('Ym').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{
     *     opening_balances: list<array{material_type: string, quantity: float, note: string}>,
     *     movements: list<array{
     *         day: int,
     *         document_day?: int,
     *         material_type: string,
     *         movement_type: string,
     *         vendor: string|null,
     *         internal_destination: string|null,
     *         purpose: string|null,
     *         quantity: float,
     *         document_number: string|null,
     *         note: string
     *     }>,
     *     approval: array{status: string}
     * }
     */
    protected function periodDataset(string $periodKey): array
    {
        return match ($periodKey) {
            '2025-12' => [
                'opening_balances' => [
                    [
                        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
                        'quantity' => 120.00,
                        'note' => 'Saldo awal Fly Ash untuk simulasi Desember 2025.',
                    ],
                    [
                        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
                        'quantity' => 80.00,
                        'note' => 'Saldo awal Bottom Ash untuk simulasi Desember 2025.',
                    ],
                ],
                'movements' => [
                    ['day' => 2, 'material_type' => 'fly_ash', 'movement_type' => FabaMovement::TYPE_PRODUCTION, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 40.00, 'document_number' => null, 'note' => 'Produksi unit 1'],
                    ['day' => 4, 'material_type' => 'bottom_ash', 'movement_type' => FabaMovement::TYPE_PRODUCTION, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 22.00, 'document_number' => null, 'note' => 'Produksi unit 2'],
                    ['day' => 8, 'material_type' => 'fly_ash', 'movement_type' => FabaMovement::TYPE_WORKSHOP, 'vendor' => null, 'internal_destination' => null, 'purpose' => 'internal_fill', 'quantity' => 12.00, 'document_number' => null, 'note' => 'Workshop pencampuran FA'],
                    ['day' => 12, 'material_type' => 'fly_ash', 'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL, 'vendor' => 'semen', 'internal_destination' => null, 'purpose' => 'cement', 'quantity' => 28.00, 'document_number' => 'DOC-FABA-202512-001', 'document_day' => 12, 'note' => 'Pemanfaatan ke industri semen'],
                    ['day' => 16, 'material_type' => 'bottom_ash', 'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL, 'vendor' => null, 'internal_destination' => 'workshop', 'purpose' => 'internal_fill', 'quantity' => 10.00, 'document_number' => null, 'note' => 'Pemanfaatan internal workshop'],
                    ['day' => 20, 'material_type' => 'fly_ash', 'movement_type' => FabaMovement::TYPE_DISPOSAL_POK, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 4.00, 'document_number' => null, 'note' => 'Disposal POK operasional'],
                    ['day' => 24, 'material_type' => 'bottom_ash', 'movement_type' => FabaMovement::TYPE_REJECT, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 3.00, 'document_number' => null, 'note' => 'Material reject akhir bulan'],
                ],
                'approval' => ['status' => FabaMonthlyApproval::STATUS_APPROVED],
            ],
            '2026-01' => [
                'opening_balances' => [
                    [
                        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
                        'quantity' => 140.00,
                        'note' => 'Saldo awal Fly Ash untuk simulasi Januari 2026.',
                    ],
                    [
                        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
                        'quantity' => 85.00,
                        'note' => 'Saldo awal Bottom Ash untuk simulasi Januari 2026.',
                    ],
                ],
                'movements' => [
                    ['day' => 3, 'material_type' => 'fly_ash', 'movement_type' => FabaMovement::TYPE_PRODUCTION, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 45.00, 'document_number' => null, 'note' => 'Produksi unit 1'],
                    ['day' => 5, 'material_type' => 'bottom_ash', 'movement_type' => FabaMovement::TYPE_PRODUCTION, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 24.00, 'document_number' => null, 'note' => 'Produksi unit 2'],
                    ['day' => 9, 'material_type' => 'fly_ash', 'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL, 'vendor' => 'konstruksi', 'internal_destination' => null, 'purpose' => 'paving', 'quantity' => 30.00, 'document_number' => 'DOC-FABA-202601-001', 'document_day' => 9, 'note' => 'Pemanfaatan ke konstruksi hijau'],
                    ['day' => 13, 'material_type' => 'bottom_ash', 'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL, 'vendor' => 'paving', 'internal_destination' => null, 'purpose' => 'paving', 'quantity' => 14.00, 'document_number' => 'DOC-FABA-202601-002', 'document_day' => 13, 'note' => 'Pemanfaatan ke paving sentosa'],
                    ['day' => 18, 'material_type' => 'fly_ash', 'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL, 'vendor' => null, 'internal_destination' => 'roadbase', 'purpose' => 'internal_fill', 'quantity' => 8.00, 'document_number' => null, 'note' => 'Roadbase internal area operasi'],
                    ['day' => 22, 'material_type' => 'bottom_ash', 'movement_type' => FabaMovement::TYPE_WORKSHOP, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 6.00, 'document_number' => null, 'note' => 'Workshop bottom ash'],
                    ['day' => 26, 'material_type' => 'fly_ash', 'movement_type' => FabaMovement::TYPE_REJECT, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 5.00, 'document_number' => null, 'note' => 'Material reject bulanan'],
                ],
                'approval' => ['status' => FabaMonthlyApproval::STATUS_APPROVED],
            ],
            default => [
                'opening_balances' => [
                    [
                        'material_type' => FabaMovement::MATERIAL_FLY_ASH,
                        'quantity' => 150.00,
                        'note' => 'Saldo awal Fly Ash untuk simulasi Februari 2026.',
                    ],
                    [
                        'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
                        'quantity' => 90.00,
                        'note' => 'Saldo awal Bottom Ash untuk simulasi Februari 2026.',
                    ],
                ],
                'movements' => [
                    ['day' => 2, 'material_type' => 'fly_ash', 'movement_type' => FabaMovement::TYPE_PRODUCTION, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 38.00, 'document_number' => null, 'note' => 'Produksi shift pagi'],
                    ['day' => 6, 'material_type' => 'bottom_ash', 'movement_type' => FabaMovement::TYPE_PRODUCTION, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 20.00, 'document_number' => null, 'note' => 'Produksi shift malam'],
                    ['day' => 10, 'material_type' => 'fly_ash', 'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL, 'vendor' => 'semen', 'internal_destination' => null, 'purpose' => 'cement', 'quantity' => 24.00, 'document_number' => 'DOC-FABA-202602-001', 'document_day' => 10, 'note' => 'Pengiriman ke PT Semen Nusantara'],
                    ['day' => 14, 'material_type' => 'bottom_ash', 'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL, 'vendor' => null, 'internal_destination' => 'workshop', 'purpose' => 'internal_fill', 'quantity' => 12.00, 'document_number' => null, 'note' => 'Kebutuhan internal workshop'],
                    ['day' => 18, 'material_type' => 'fly_ash', 'movement_type' => FabaMovement::TYPE_DISPOSAL_POK, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 6.00, 'document_number' => null, 'note' => 'POK operasional Februari'],
                    ['day' => 22, 'material_type' => 'bottom_ash', 'movement_type' => FabaMovement::TYPE_WORKSHOP, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 5.00, 'document_number' => null, 'note' => 'Workshop BA untuk internal'],
                    ['day' => 25, 'material_type' => 'fly_ash', 'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL, 'vendor' => 'paving', 'internal_destination' => null, 'purpose' => 'paving', 'quantity' => 10.00, 'document_number' => 'DOC-FABA-202602-002', 'document_day' => 25, 'note' => 'Pemanfaatan ke paving block'],
                ],
                'approval' => ['status' => FabaMonthlyApproval::STATUS_SUBMITTED],
            ],
        };
    }
}
