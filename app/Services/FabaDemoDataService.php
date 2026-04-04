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

        $this->guardProtectedDemoFreshReset($tenantCode, $schemaName, $freshTenant);

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
                'name' => 'TWMS Integrated Demo',
                'schema_name' => $schemaName,
                'description' => 'Tenant demo terintegrasi untuk simulasi modul limbah umum dan FABA.',
                'address' => 'Area Demo TWMS Terpadu',
                'phone' => '0210000000',
                'email' => 'twms.demo@local.test',
                'is_active' => true,
            ]);

            return $organization->fresh();
        }

        return Organization::query()->create([
            'name' => 'TWMS Integrated Demo',
            'code' => $tenantCode,
            'schema_name' => $schemaName,
            'description' => 'Tenant demo terintegrasi untuk simulasi modul limbah umum dan FABA.',
            'address' => 'Area Demo TWMS Terpadu',
            'phone' => '0210000000',
            'email' => 'twms.demo@local.test',
            'is_active' => true,
        ]);
    }

    /**
     * @return array{
     *     operator: \App\Models\User,
     *     supervisor: \App\Models\User,
     *     super_admin: \App\Models\User
     * }
     */
    protected function upsertUsers(Organization $organization): array
    {
        $superAdminRoleId = Role::query()->where('slug', 'super_admin')->value('id');
        $supervisorRoleId = Role::query()->where('slug', 'supervisor')->value('id');
        $operatorRoleId = Role::query()->where('slug', 'operator')->value('id');

        if (! $superAdminRoleId || ! $supervisorRoleId || ! $operatorRoleId) {
            throw new RuntimeException('Role super_admin/supervisor/operator belum tersedia. Jalankan seed role terlebih dahulu.');
        }

        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'john@d.co'],
            [
                'name' => 'John',
                'password' => 'password',
                'organization_id' => $organization->id,
                'role_id' => $superAdminRoleId,
                'is_super_admin' => true,
            ]
        );
        $superAdmin->forceFill(['email_verified_at' => now()])->save();

        $supervisor = User::query()->updateOrCreate(
            ['email' => 'faba.supervisor.demo@local.test'],
            [
                'name' => 'Supervisor FABA Demo',
                'password' => 'password',
                'organization_id' => $organization->id,
                'role_id' => $supervisorRoleId,
                'is_super_admin' => false,
            ]
        );
        $supervisor->forceFill(['email_verified_at' => now()])->save();

        $operator = User::query()->updateOrCreate(
            ['email' => 'faba.operator.demo@local.test'],
            [
                'name' => 'Operator FABA Demo',
                'password' => 'password',
                'organization_id' => $organization->id,
                'role_id' => $operatorRoleId,
                'is_super_admin' => false,
            ]
        );
        $operator->forceFill(['email_verified_at' => now()])->save();

        return [
            'super_admin' => $superAdmin,
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

    protected function guardProtectedDemoFreshReset(string $tenantCode, string $schemaName, bool $freshTenant): void
    {
        if (! $freshTenant) {
            return;
        }

        if (
            $tenantCode === self::DEFAULT_TENANT_CODE
            && $schemaName === self::DEFAULT_SCHEMA_NAME
            && ! $this->tenantService->usesDedicatedTestingDatabase()
        ) {
            throw new RuntimeException(sprintf(
                'Mode --fresh-tenant untuk schema demo default [%s] hanya diizinkan pada database testing. Database aktif: [%s].',
                $schemaName,
                config('database.connections.'.config('database.default').'.database'),
            ));
        }
    }

    /**
     * @return list<CarbonImmutable>
     */
    protected function getSimulationPeriods(): array
    {
        $startOfCurrentMonth = CarbonImmutable::now()->startOfMonth();

        return collect(range(12, 1))
            ->map(fn (int $monthsBack): CarbonImmutable => $startOfCurrentMonth->subMonthsNoOverflow($monthsBack))
            ->all();
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
            $dataset = $this->periodDataset($period, $index, count($periods));
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
                'rejected_by' => $dataset['approval']['status'] === FabaMonthlyApproval::STATUS_REJECTED ? $supervisor->id : null,
                'rejected_at' => $dataset['approval']['status'] === FabaMonthlyApproval::STATUS_REJECTED ? $period->endOfMonth()->setTime(16, 45) : null,
                'rejection_note' => $dataset['approval']['status'] === FabaMonthlyApproval::STATUS_REJECTED
                    ? ($dataset['approval']['rejection_note'] ?? 'Dokumen pendukung perlu diperbaiki.')
                    : null,
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

            if ($approval->status === FabaMonthlyApproval::STATUS_REJECTED) {
                $this->fabaAuditService->log(
                    $supervisor->id,
                    'reject',
                    FabaAuditLog::MODULE_APPROVAL,
                    FabaMonthlyApproval::class,
                    $approval->id,
                    $year,
                    $month,
                    'Periode demo ditolak untuk revisi.',
                    [
                        'status' => $approval->status,
                        'rejection_note' => $approval->rejection_note,
                    ]
                );
            }

            if ($dataset['approval']['reopened'] ?? false) {
                $this->fabaAuditService->log(
                    $supervisor->id,
                    'reopen',
                    FabaAuditLog::MODULE_APPROVAL,
                    FabaMonthlyApproval::class,
                    $approval->id,
                    $year,
                    $month,
                    'Periode demo dibuka kembali untuk audit dokumen.',
                    ['status' => $approval->status]
                );

                $this->fabaAuditService->log(
                    $operator->id,
                    'resubmit',
                    FabaAuditLog::MODULE_APPROVAL,
                    FabaMonthlyApproval::class,
                    $approval->id,
                    $year,
                    $month,
                    'Periode demo diajukan ulang setelah reopen.',
                    ['status' => $approval->status]
                );

                if ($approval->status === FabaMonthlyApproval::STATUS_APPROVED) {
                    $this->fabaAuditService->log(
                        $supervisor->id,
                        'reapprove',
                        FabaAuditLog::MODULE_APPROVAL,
                        FabaMonthlyApproval::class,
                        $approval->id,
                        $year,
                        $month,
                        'Periode demo disetujui ulang setelah verifikasi dokumen.',
                        ['status' => $approval->status]
                    );
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
        if (in_array($movementType, [
            FabaMovement::TYPE_ADJUSTMENT_IN,
            FabaMovement::TYPE_ADJUSTMENT_OUT,
        ], true)) {
            return FabaAuditLog::MODULE_ADJUSTMENT;
        }

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
     *     approval: array{
     *         status: string,
     *         rejection_note?: string,
     *         reopened?: bool
     *     }
     * }
     */
    protected function periodDataset(CarbonImmutable $period, int $periodIndex, int $periodCount): array
    {
        $periodKey = $period->format('Ym');
        $flyOpeningBalance = 120 + ($periodIndex * 9);
        $bottomOpeningBalance = 80 + ($periodIndex * 5);
        $vendorKeys = ['semen', 'konstruksi', 'paving'];
        $purposeKeys = ['cement', 'paving', 'internal_fill'];
        $internalDestinationKeys = ['workshop', 'roadbase'];
        $externalVendor = $vendorKeys[$periodIndex % count($vendorKeys)];
        $secondaryVendor = $vendorKeys[($periodIndex + 1) % count($vendorKeys)];
        $purpose = $purposeKeys[$periodIndex % count($purposeKeys)];
        $secondaryPurpose = $purposeKeys[($periodIndex + 1) % count($purposeKeys)];
        $internalDestination = $internalDestinationKeys[$periodIndex % count($internalDestinationKeys)];
        $secondaryInternalDestination = $internalDestinationKeys[($periodIndex + 1) % count($internalDestinationKeys)];
        $specialOutflowType = match ($periodIndex % 3) {
            0 => FabaMovement::TYPE_DISPOSAL_POK,
            1 => FabaMovement::TYPE_REJECT,
            default => FabaMovement::TYPE_UTILIZATION_EXTERNAL,
        };
        $specialOutflowVendor = $specialOutflowType === FabaMovement::TYPE_UTILIZATION_EXTERNAL ? $secondaryVendor : null;
        $specialOutflowPurpose = $specialOutflowType === FabaMovement::TYPE_UTILIZATION_EXTERNAL ? $secondaryPurpose : null;
        $specialOutflowDocument = $specialOutflowType === FabaMovement::TYPE_UTILIZATION_EXTERNAL
            ? sprintf('DOC-FABA-%s-ALT', $periodKey)
            : null;
        $adjustmentType = $periodIndex % 2 === 0 ? FabaMovement::TYPE_ADJUSTMENT_IN : FabaMovement::TYPE_ADJUSTMENT_OUT;
        $approvalStatus = match (true) {
            $periodIndex === 4 => FabaMonthlyApproval::STATUS_REJECTED,
            $periodIndex === $periodCount - 1 => FabaMonthlyApproval::STATUS_SUBMITTED,
            default => FabaMonthlyApproval::STATUS_APPROVED,
        };

        return [
            'opening_balances' => [
                [
                    'material_type' => FabaMovement::MATERIAL_FLY_ASH,
                    'quantity' => (float) $flyOpeningBalance,
                    'note' => 'Saldo awal Fly Ash untuk simulasi '.$period->translatedFormat('F Y').'.',
                ],
                [
                    'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH,
                    'quantity' => (float) $bottomOpeningBalance,
                    'note' => 'Saldo awal Bottom Ash untuk simulasi '.$period->translatedFormat('F Y').'.',
                ],
            ],
            'movements' => [
                ['day' => 2, 'material_type' => FabaMovement::MATERIAL_FLY_ASH, 'movement_type' => FabaMovement::TYPE_PRODUCTION, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 40.0 + ($periodIndex * 2), 'document_number' => null, 'note' => 'Produksi Fly Ash periodik'],
                ['day' => 4, 'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH, 'movement_type' => FabaMovement::TYPE_PRODUCTION, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 24.0 + $periodIndex, 'document_number' => null, 'note' => 'Produksi Bottom Ash periodik'],
                ['day' => 7, 'material_type' => $periodIndex % 2 === 0 ? FabaMovement::MATERIAL_FLY_ASH : FabaMovement::MATERIAL_BOTTOM_ASH, 'movement_type' => FabaMovement::TYPE_WORKSHOP, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 6.0 + ($periodIndex % 4), 'document_number' => null, 'note' => 'Pemanfaatan workshop internal'],
                ['day' => 10, 'material_type' => FabaMovement::MATERIAL_FLY_ASH, 'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL, 'vendor' => $externalVendor, 'internal_destination' => null, 'purpose' => $purpose, 'quantity' => 18.0 + $periodIndex, 'document_number' => sprintf('DOC-FABA-%s-001', $periodKey), 'document_day' => 10, 'note' => 'Pemanfaatan external utama'],
                ['day' => 14, 'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH, 'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL, 'vendor' => null, 'internal_destination' => $internalDestination, 'purpose' => 'internal_fill', 'quantity' => 9.0 + ($periodIndex % 3), 'document_number' => null, 'note' => 'Pemanfaatan internal area operasi'],
                ['day' => 18, 'material_type' => FabaMovement::MATERIAL_FLY_ASH, 'movement_type' => $specialOutflowType, 'vendor' => $specialOutflowVendor, 'internal_destination' => null, 'purpose' => $specialOutflowPurpose, 'quantity' => 5.0 + ($periodIndex % 5), 'document_number' => $specialOutflowDocument, 'document_day' => 18, 'note' => 'Outflow operasional khusus'],
                ['day' => 21, 'material_type' => $adjustmentType === FabaMovement::TYPE_ADJUSTMENT_IN ? FabaMovement::MATERIAL_BOTTOM_ASH : FabaMovement::MATERIAL_FLY_ASH, 'movement_type' => $adjustmentType, 'vendor' => null, 'internal_destination' => null, 'purpose' => null, 'quantity' => 3.0 + ($periodIndex % 4), 'document_number' => null, 'note' => 'Penyesuaian stok periodik'],
                ['day' => 24, 'material_type' => FabaMovement::MATERIAL_BOTTOM_ASH, 'movement_type' => FabaMovement::TYPE_UTILIZATION_EXTERNAL, 'vendor' => $secondaryVendor, 'internal_destination' => null, 'purpose' => $secondaryPurpose, 'quantity' => 7.0 + ($periodIndex % 4), 'document_number' => sprintf('DOC-FABA-%s-002', $periodKey), 'document_day' => 24, 'note' => 'Pemanfaatan external lanjutan'],
                ['day' => 26, 'material_type' => FabaMovement::MATERIAL_FLY_ASH, 'movement_type' => FabaMovement::TYPE_UTILIZATION_INTERNAL, 'vendor' => null, 'internal_destination' => $secondaryInternalDestination, 'purpose' => 'internal_fill', 'quantity' => 4.0 + ($periodIndex % 3), 'document_number' => null, 'note' => 'Distribusi internal lanjutan'],
            ],
            'approval' => [
                'status' => $approvalStatus,
                'rejection_note' => $approvalStatus === FabaMonthlyApproval::STATUS_REJECTED
                    ? 'Perlu koreksi dokumen manifest dan angka penyesuaian stok.'
                    : null,
                'reopened' => $periodIndex === 8,
            ],
        ];
    }
}
