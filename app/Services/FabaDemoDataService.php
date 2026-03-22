<?php

namespace App\Services;

use App\Models\FabaAuditLog;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaOpeningBalance;
use App\Models\FabaProductionEntry;
use App\Models\FabaUtilizationEntry;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use RuntimeException;

class FabaDemoDataService
{
    public const DEFAULT_TENANT_CODE = 'TWMSDEMO';

    public const DEFAULT_SCHEMA_NAME = 'tenant_twms_demo';

    public function __construct(
        protected TenantService $tenantService,
        protected FabaAuditService $fabaAuditService
    ) {}

    /**
     * @return array{
     *     organization: \App\Models\Organization,
     *     schema_name: string,
     *     periods: list<string>,
     *     vendors_count: int,
     *     production_count: int,
     *     utilization_count: int,
     *     approvals_count: int,
     *     opening_balances_count: int
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

            if (! $freshTenant && $this->datasetAlreadyExists($periods)) {
                throw new RuntimeException('Dataset demo FABA untuk 3 bulan terakhir sudah ada pada tenant target. Gunakan --fresh-tenant untuk mengulang dari awal.');
            }

            $vendors = $this->createVendors($users['supervisor']);
            $summary = $this->seedDataset($periods, $users['operator'], $users['supervisor'], $vendors);
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
            ...$summary,
        ];
    }

    protected function seedRolesAndPermissions(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolesSeeder', '--no-interaction' => true]);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermissionsSeeder', '--no-interaction' => true]);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionsSeeder', '--no-interaction' => true]);
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
                'description' => 'Tenant demo untuk simulasi modul FABA 3 bulan terakhir.',
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
            'description' => 'Tenant demo untuk simulasi modul FABA 3 bulan terakhir.',
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
                FabaProductionEntry::query()->forPeriod($year, $month)->exists()
                || FabaUtilizationEntry::query()->forPeriod($year, $month)->exists()
                || FabaMonthlyApproval::query()->forPeriod($year, $month)->exists()
            ) {
                return true;
            }
        }

        return false;
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
     * @param  list<CarbonImmutable>  $periods
     * @param  array<string, \App\Models\Vendor>  $vendors
     * @return array{
     *     vendors_count: int,
     *     production_count: int,
     *     utilization_count: int,
     *     approvals_count: int,
     *     opening_balances_count: int
     * }
     */
    protected function seedDataset(
        array $periods,
        User $operator,
        User $supervisor,
        array $vendors,
    ): array {
        $openingBalancesCount = 0;
        $productionCount = 0;
        $utilizationCount = 0;
        $approvalsCount = 0;

        foreach ($periods as $index => $period) {
            $key = $period->format('Y-m');
            $year = (int) $period->format('Y');
            $month = (int) $period->format('n');
            $dataset = $this->periodDataset($key);

            if (Arr::get($dataset, 'opening_balances')) {
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
            }

            foreach ($dataset['production_entries'] as $entryIndex => $productionEntry) {
                $entry = FabaProductionEntry::query()->create([
                    'entry_number' => $this->makeEntryNumber('FP', $period, $entryIndex + 1),
                    'transaction_date' => $period->setDay($productionEntry['day'])->toDateString(),
                    'material_type' => $productionEntry['material_type'],
                    'entry_type' => $productionEntry['entry_type'],
                    'quantity' => $productionEntry['quantity'],
                    'unit' => FabaProductionEntry::DEFAULT_UNIT,
                    'note' => $productionEntry['note'],
                    'created_by' => $operator->id,
                    'updated_by' => $operator->id,
                ]);

                $this->fabaAuditService->log(
                    $operator->id,
                    'create',
                    FabaAuditLog::MODULE_PRODUCTION,
                    FabaProductionEntry::class,
                    $entry->id,
                    $year,
                    $month,
                    'Transaksi produksi demo dibuat.',
                    [
                        'entry_number' => $entry->entry_number,
                        'material_type' => $entry->material_type,
                        'entry_type' => $entry->entry_type,
                        'quantity' => (float) $entry->quantity,
                    ]
                );

                $productionCount++;
            }

            foreach ($dataset['utilization_entries'] as $entryIndex => $utilizationEntry) {
                $vendor = $utilizationEntry['vendor'] ? $vendors[$utilizationEntry['vendor']] : null;
                $documentDate = $period->setDay($utilizationEntry['document_day'])->toDateString();
                $entry = FabaUtilizationEntry::query()->create([
                    'entry_number' => $this->makeEntryNumber('FU', $period, $entryIndex + 1),
                    'transaction_date' => $period->setDay($utilizationEntry['day'])->toDateString(),
                    'material_type' => $utilizationEntry['material_type'],
                    'utilization_type' => $utilizationEntry['utilization_type'],
                    'vendor_id' => $vendor?->id,
                    'quantity' => $utilizationEntry['quantity'],
                    'unit' => FabaUtilizationEntry::DEFAULT_UNIT,
                    'document_number' => $utilizationEntry['document_number'],
                    'document_date' => $utilizationEntry['utilization_type'] === FabaUtilizationEntry::TYPE_EXTERNAL ? $documentDate : null,
                    'attachment_path' => null,
                    'note' => $utilizationEntry['note'],
                    'created_by' => $operator->id,
                    'updated_by' => $operator->id,
                ]);

                $this->fabaAuditService->log(
                    $operator->id,
                    'create',
                    FabaAuditLog::MODULE_UTILIZATION,
                    FabaUtilizationEntry::class,
                    $entry->id,
                    $year,
                    $month,
                    'Transaksi pemanfaatan demo dibuat.',
                    [
                        'entry_number' => $entry->entry_number,
                        'material_type' => $entry->material_type,
                        'utilization_type' => $entry->utilization_type,
                        'quantity' => (float) $entry->quantity,
                    ]
                );

                $utilizationCount++;
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
            'vendors_count' => count($vendors),
            'production_count' => $productionCount,
            'utilization_count' => $utilizationCount,
            'approvals_count' => $approvalsCount,
            'opening_balances_count' => $openingBalancesCount,
        ];
    }

    protected function makeEntryNumber(string $prefix, CarbonImmutable $period, int $sequence): string
    {
        return $prefix.'-'.$period->format('Ym').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{
     *     opening_balances?: list<array{material_type: string, quantity: float, note: string}>,
     *     production_entries: list<array{day: int, material_type: string, entry_type: string, quantity: float, note: string}>,
     *     utilization_entries: list<array{day: int, document_day: int, material_type: string, utilization_type: string, vendor: string|null, quantity: float, document_number: string|null, note: string}>,
     *     approval: array{status: string}
     * }
     */
    protected function periodDataset(string $periodKey): array
    {
        return match ($periodKey) {
            '2025-12' => [
                'opening_balances' => [
                    [
                        'material_type' => FabaProductionEntry::MATERIAL_FLY_ASH,
                        'quantity' => 120.00,
                        'note' => 'Saldo awal Fly Ash untuk simulasi Desember 2025.',
                    ],
                    [
                        'material_type' => FabaProductionEntry::MATERIAL_BOTTOM_ASH,
                        'quantity' => 80.00,
                        'note' => 'Saldo awal Bottom Ash untuk simulasi Desember 2025.',
                    ],
                ],
                'production_entries' => [
                    ['day' => 2, 'material_type' => 'fly_ash', 'entry_type' => 'production', 'quantity' => 40.00, 'note' => 'Produksi unit 1'],
                    ['day' => 4, 'material_type' => 'fly_ash', 'entry_type' => 'production', 'quantity' => 35.00, 'note' => 'Produksi unit 2'],
                    ['day' => 7, 'material_type' => 'fly_ash', 'entry_type' => 'production', 'quantity' => 22.00, 'note' => 'Produksi reguler'],
                    ['day' => 11, 'material_type' => 'fly_ash', 'entry_type' => 'pok', 'quantity' => 18.00, 'note' => 'POK Fly Ash'],
                    ['day' => 18, 'material_type' => 'fly_ash', 'entry_type' => 'workshop', 'quantity' => 24.00, 'note' => 'Kegiatan workshop'],
                    ['day' => 23, 'material_type' => 'fly_ash', 'entry_type' => 'reject', 'quantity' => 16.00, 'note' => 'Reject produksi'],
                    ['day' => 3, 'material_type' => 'bottom_ash', 'entry_type' => 'production', 'quantity' => 32.00, 'note' => 'Produksi bottom ash awal bulan'],
                    ['day' => 8, 'material_type' => 'bottom_ash', 'entry_type' => 'production', 'quantity' => 28.00, 'note' => 'Produksi bottom ash rutin'],
                    ['day' => 15, 'material_type' => 'bottom_ash', 'entry_type' => 'production', 'quantity' => 20.00, 'note' => 'Produksi bottom ash tengah bulan'],
                    ['day' => 20, 'material_type' => 'bottom_ash', 'entry_type' => 'workshop', 'quantity' => 9.00, 'note' => 'Workshop bottom ash'],
                    ['day' => 26, 'material_type' => 'bottom_ash', 'entry_type' => 'reject', 'quantity' => 6.00, 'note' => 'Reject bottom ash'],
                ],
                'utilization_entries' => [
                    ['day' => 5, 'document_day' => 5, 'material_type' => 'fly_ash', 'utilization_type' => 'external', 'vendor' => 'semen', 'quantity' => 24.00, 'document_number' => 'DOC-DEC-001', 'note' => 'Pengiriman ke industri semen'],
                    ['day' => 10, 'document_day' => 10, 'material_type' => 'fly_ash', 'utilization_type' => 'internal', 'vendor' => null, 'quantity' => 18.00, 'document_number' => null, 'note' => 'Pemanfaatan internal area utilitas'],
                    ['day' => 14, 'document_day' => 14, 'material_type' => 'fly_ash', 'utilization_type' => 'external', 'vendor' => 'konstruksi', 'quantity' => 16.00, 'document_number' => 'DOC-DEC-002', 'note' => 'Pemanfaatan proyek konstruksi'],
                    ['day' => 19, 'document_day' => 19, 'material_type' => 'fly_ash', 'utilization_type' => 'internal', 'vendor' => null, 'quantity' => 22.00, 'document_number' => null, 'note' => 'Pemanfaatan internal gudang material'],
                    ['day' => 25, 'document_day' => 25, 'material_type' => 'fly_ash', 'utilization_type' => 'external', 'vendor' => 'semen', 'quantity' => 25.00, 'document_number' => 'DOC-DEC-003', 'note' => 'Pengiriman batch akhir bulan'],
                    ['day' => 6, 'document_day' => 6, 'material_type' => 'bottom_ash', 'utilization_type' => 'external', 'vendor' => 'paving', 'quantity' => 15.00, 'document_number' => 'DOC-DEC-004', 'note' => 'Bottom ash untuk paving'],
                    ['day' => 16, 'document_day' => 16, 'material_type' => 'bottom_ash', 'utilization_type' => 'internal', 'vendor' => null, 'quantity' => 17.00, 'document_number' => null, 'note' => 'Pemanfaatan internal penataan area'],
                    ['day' => 27, 'document_day' => 27, 'material_type' => 'bottom_ash', 'utilization_type' => 'internal', 'vendor' => null, 'quantity' => 18.00, 'document_number' => null, 'note' => 'Pemanfaatan internal penimbunan terkontrol'],
                ],
                'approval' => [
                    'status' => FabaMonthlyApproval::STATUS_APPROVED,
                ],
            ],
            '2026-01' => [
                'opening_balances' => [
                    [
                        'material_type' => FabaProductionEntry::MATERIAL_FLY_ASH,
                        'quantity' => 170.00,
                        'note' => 'Carry forward Fly Ash dari Desember 2025.',
                    ],
                    [
                        'material_type' => FabaProductionEntry::MATERIAL_BOTTOM_ASH,
                        'quantity' => 125.00,
                        'note' => 'Carry forward Bottom Ash dari Desember 2025.',
                    ],
                ],
                'production_entries' => [
                    ['day' => 2, 'material_type' => 'fly_ash', 'entry_type' => 'production', 'quantity' => 45.00, 'note' => 'Produksi awal Januari'],
                    ['day' => 5, 'material_type' => 'fly_ash', 'entry_type' => 'production', 'quantity' => 40.00, 'note' => 'Produksi rutin'],
                    ['day' => 8, 'material_type' => 'fly_ash', 'entry_type' => 'production', 'quantity' => 30.00, 'note' => 'Produksi tambahan'],
                    ['day' => 11, 'material_type' => 'fly_ash', 'entry_type' => 'pok', 'quantity' => 25.00, 'note' => 'POK Fly Ash Januari'],
                    ['day' => 18, 'material_type' => 'fly_ash', 'entry_type' => 'workshop', 'quantity' => 22.00, 'note' => 'Workshop pemanfaatan FA'],
                    ['day' => 24, 'material_type' => 'fly_ash', 'entry_type' => 'reject', 'quantity' => 18.00, 'note' => 'Reject Fly Ash Januari'],
                    ['day' => 3, 'material_type' => 'bottom_ash', 'entry_type' => 'production', 'quantity' => 38.00, 'note' => 'Produksi BA awal bulan'],
                    ['day' => 9, 'material_type' => 'bottom_ash', 'entry_type' => 'production', 'quantity' => 30.00, 'note' => 'Produksi BA reguler'],
                    ['day' => 14, 'material_type' => 'bottom_ash', 'entry_type' => 'production', 'quantity' => 24.00, 'note' => 'Produksi BA mingguan'],
                    ['day' => 20, 'material_type' => 'bottom_ash', 'entry_type' => 'production', 'quantity' => 18.00, 'note' => 'Produksi BA tambahan'],
                    ['day' => 25, 'material_type' => 'bottom_ash', 'entry_type' => 'workshop', 'quantity' => 6.00, 'note' => 'Workshop BA'],
                    ['day' => 29, 'material_type' => 'bottom_ash', 'entry_type' => 'reject', 'quantity' => 4.00, 'note' => 'Reject BA Januari'],
                ],
                'utilization_entries' => [
                    ['day' => 6, 'document_day' => 6, 'material_type' => 'fly_ash', 'utilization_type' => 'external', 'vendor' => 'semen', 'quantity' => 35.00, 'document_number' => 'DOC-JAN-001', 'note' => 'Pengiriman utama ke industri semen'],
                    ['day' => 11, 'document_day' => 11, 'material_type' => 'fly_ash', 'utilization_type' => 'internal', 'vendor' => null, 'quantity' => 24.00, 'document_number' => null, 'note' => 'Pemanfaatan internal jalan akses'],
                    ['day' => 15, 'document_day' => 15, 'material_type' => 'fly_ash', 'utilization_type' => 'external', 'vendor' => 'konstruksi', 'quantity' => 28.00, 'document_number' => 'DOC-JAN-002', 'note' => 'Proyek konstruksi hijau'],
                    ['day' => 20, 'document_day' => 20, 'material_type' => 'fly_ash', 'utilization_type' => 'internal', 'vendor' => null, 'quantity' => 26.00, 'document_number' => null, 'note' => 'Pemanfaatan internal area penyangga'],
                    ['day' => 26, 'document_day' => 26, 'material_type' => 'fly_ash', 'utilization_type' => 'external', 'vendor' => 'semen', 'quantity' => 27.00, 'document_number' => 'DOC-JAN-003', 'note' => 'Batch akhir bulan ke vendor semen'],
                    ['day' => 8, 'document_day' => 8, 'material_type' => 'bottom_ash', 'utilization_type' => 'external', 'vendor' => 'paving', 'quantity' => 30.00, 'document_number' => 'DOC-JAN-004', 'note' => 'Bottom ash untuk paving block'],
                    ['day' => 18, 'document_day' => 18, 'material_type' => 'bottom_ash', 'utilization_type' => 'internal', 'vendor' => null, 'quantity' => 32.00, 'document_number' => null, 'note' => 'Pemanfaatan BA internal'],
                    ['day' => 28, 'document_day' => 28, 'material_type' => 'bottom_ash', 'utilization_type' => 'internal', 'vendor' => null, 'quantity' => 33.00, 'document_number' => null, 'note' => 'Pemanfaatan BA penutup bulan'],
                ],
                'approval' => [
                    'status' => FabaMonthlyApproval::STATUS_APPROVED,
                ],
            ],
            '2026-02' => [
                'production_entries' => [
                    ['day' => 2, 'material_type' => 'fly_ash', 'entry_type' => 'production', 'quantity' => 36.00, 'note' => 'Produksi FA awal Februari'],
                    ['day' => 5, 'material_type' => 'fly_ash', 'entry_type' => 'production', 'quantity' => 30.00, 'note' => 'Produksi FA reguler'],
                    ['day' => 9, 'material_type' => 'fly_ash', 'entry_type' => 'production', 'quantity' => 22.00, 'note' => 'Produksi FA tambahan'],
                    ['day' => 12, 'material_type' => 'fly_ash', 'entry_type' => 'pok', 'quantity' => 18.00, 'note' => 'POK Februari'],
                    ['day' => 18, 'material_type' => 'fly_ash', 'entry_type' => 'workshop', 'quantity' => 14.00, 'note' => 'Workshop FA Februari'],
                    ['day' => 24, 'material_type' => 'fly_ash', 'entry_type' => 'reject', 'quantity' => 10.00, 'note' => 'Reject FA Februari'],
                    ['day' => 3, 'material_type' => 'bottom_ash', 'entry_type' => 'production', 'quantity' => 34.00, 'note' => 'Produksi BA awal Februari'],
                    ['day' => 8, 'material_type' => 'bottom_ash', 'entry_type' => 'production', 'quantity' => 24.00, 'note' => 'Produksi BA reguler'],
                    ['day' => 15, 'material_type' => 'bottom_ash', 'entry_type' => 'production', 'quantity' => 20.00, 'note' => 'Produksi BA minggu ketiga'],
                    ['day' => 20, 'material_type' => 'bottom_ash', 'entry_type' => 'production', 'quantity' => 14.00, 'note' => 'Produksi BA penyeimbang'],
                    ['day' => 25, 'material_type' => 'bottom_ash', 'entry_type' => 'workshop', 'quantity' => 5.00, 'note' => 'Workshop BA Februari'],
                    ['day' => 27, 'material_type' => 'bottom_ash', 'entry_type' => 'reject', 'quantity' => 3.00, 'note' => 'Reject BA Februari'],
                ],
                'utilization_entries' => [
                    ['day' => 4, 'document_day' => 4, 'material_type' => 'fly_ash', 'utilization_type' => 'external', 'vendor' => 'semen', 'quantity' => 42.00, 'document_number' => 'DOC-FEB-001', 'note' => 'Pengiriman besar ke vendor semen'],
                    ['day' => 9, 'document_day' => 9, 'material_type' => 'fly_ash', 'utilization_type' => 'internal', 'vendor' => null, 'quantity' => 30.00, 'document_number' => null, 'note' => 'Pemanfaatan internal FA'],
                    ['day' => 13, 'document_day' => 13, 'material_type' => 'fly_ash', 'utilization_type' => 'external', 'vendor' => 'konstruksi', 'quantity' => 36.00, 'document_number' => 'DOC-FEB-002', 'note' => 'Pemanfaatan konstruksi aktif'],
                    ['day' => 18, 'document_day' => 18, 'material_type' => 'fly_ash', 'utilization_type' => 'internal', 'vendor' => null, 'quantity' => 27.00, 'document_number' => null, 'note' => 'Pemanfaatan internal area operasi'],
                    ['day' => 25, 'document_day' => 25, 'material_type' => 'fly_ash', 'utilization_type' => 'external', 'vendor' => 'semen', 'quantity' => 30.00, 'document_number' => 'DOC-FEB-003', 'note' => 'Batch akhir FA Februari'],
                    ['day' => 7, 'document_day' => 7, 'material_type' => 'bottom_ash', 'utilization_type' => 'external', 'vendor' => 'paving', 'quantity' => 40.00, 'document_number' => 'DOC-FEB-004', 'note' => 'Paving block skala besar'],
                    ['day' => 14, 'document_day' => 14, 'material_type' => 'bottom_ash', 'utilization_type' => 'internal', 'vendor' => null, 'quantity' => 28.00, 'document_number' => null, 'note' => 'Pemanfaatan BA internal'],
                    ['day' => 21, 'document_day' => 21, 'material_type' => 'bottom_ash', 'utilization_type' => 'internal', 'vendor' => null, 'quantity' => 30.00, 'document_number' => null, 'note' => 'Pemanfaatan BA penyangga operasional'],
                    ['day' => 26, 'document_day' => 26, 'material_type' => 'bottom_ash', 'utilization_type' => 'external', 'vendor' => 'konstruksi', 'quantity' => 22.00, 'document_number' => 'DOC-FEB-005', 'note' => 'Bottom ash untuk proyek konstruksi'],
                ],
                'approval' => [
                    'status' => FabaMonthlyApproval::STATUS_SUBMITTED,
                ],
            ],
            default => throw new RuntimeException("Dataset demo belum dikonfigurasi untuk periode {$periodKey}."),
        };
    }
}
