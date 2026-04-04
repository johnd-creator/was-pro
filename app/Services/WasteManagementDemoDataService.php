<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\WasteCategory;
use App\Models\WasteCharacteristic;
use App\Models\WasteRecord;
use App\Models\WasteTransportation;
use App\Models\WasteType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use RuntimeException;

class WasteManagementDemoDataService
{
    public const DEFAULT_TENANT_CODE = 'TWMSDEMO';

    public const DEFAULT_SCHEMA_NAME = 'tenant_twms_demo';

    public function __construct(protected TenantService $tenantService) {}

    /**
     * @return array{
     *     organization: \App\Models\Organization,
     *     schema_name: string,
     *     periods: list<string>,
     *     categories_count: int,
     *     characteristics_count: int,
     *     waste_types_count: int,
     *     vendors_count: int,
     *     waste_records_count: int,
     *     transportations_count: int
     * }
     */
    public function seedDemoData(?string $tenantCode = null, ?string $schemaName = null, bool $freshTenant = false): array
    {
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
        $periods = $this->simulationPeriods();

        try {
            $this->tenantService->switchToSchema($schemaName);

            if (! $freshTenant && $this->datasetAlreadyExists($periods)) {
                throw new RuntimeException('Dataset demo limbah umum untuk 12 bulan penuh terakhir sudah ada pada tenant target. Gunakan --fresh-tenant untuk mengulang dari awal.');
            }

            $master = $this->seedMasterData($users['supervisor']);
            $summary = $this->seedWasteRecordsAndTransportations($periods, $users['operator'], $users['supervisor'], $master['waste_types'], $master['vendors'], $tenantCode);
        } finally {
            $this->tenantService->switchToPublic();
        }

        return [
            'organization' => $organization,
            'schema_name' => $schemaName,
            'periods' => array_map(fn (CarbonImmutable $period): string => $period->format('Y-m'), $periods),
            'categories_count' => count($master['categories']),
            'characteristics_count' => count($master['characteristics']),
            'waste_types_count' => count($master['waste_types']),
            'vendors_count' => count($master['vendors']),
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
                'description' => 'Tenant demo terintegrasi untuk modul limbah umum dan FABA.',
                'address' => 'Area Demo TWMS Terpadu',
                'phone' => '0219999999',
                'email' => 'twms.demo@local.test',
                'is_active' => true,
            ]);

            return $organization->fresh();
        }

        return Organization::query()->create([
            'name' => 'TWMS Integrated Demo',
            'code' => $tenantCode,
            'schema_name' => $schemaName,
            'description' => 'Tenant demo terintegrasi untuk modul limbah umum dan FABA.',
            'address' => 'Area Demo TWMS Terpadu',
            'phone' => '0219999999',
            'email' => 'twms.demo@local.test',
            'is_active' => true,
        ]);
    }

    /**
     * @return array{
     *     supervisor: \App\Models\User,
     *     operator: \App\Models\User,
     *     super_admin: \App\Models\User
     * }
     */
    protected function upsertUsers(Organization $organization): array
    {
        $superAdminRoleId = Role::query()->where('slug', 'super_admin')->value('id');
        $supervisorRoleId = Role::query()->where('slug', 'supervisor')->value('id');
        $operatorRoleId = Role::query()->where('slug', 'operator')->value('id');

        if (! $superAdminRoleId || ! $supervisorRoleId || ! $operatorRoleId) {
            throw new RuntimeException('Role super_admin/supervisor/operator belum tersedia.');
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
            ['email' => 'wm.supervisor.demo@local.test'],
            [
                'name' => 'Supervisor Waste Demo',
                'password' => 'password',
                'organization_id' => $organization->id,
                'role_id' => $supervisorRoleId,
                'is_super_admin' => false,
            ]
        );
        $supervisor->forceFill(['email_verified_at' => now()])->save();

        $operator = User::query()->updateOrCreate(
            ['email' => 'wm.operator.demo@local.test'],
            [
                'name' => 'Operator Waste Demo',
                'password' => 'password',
                'organization_id' => $organization->id,
                'role_id' => $operatorRoleId,
                'is_super_admin' => false,
            ]
        );
        $operator->forceFill(['email_verified_at' => now()])->save();

        return [
            'super_admin' => $superAdmin,
            'supervisor' => $supervisor,
            'operator' => $operator,
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
    protected function simulationPeriods(): array
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
                WasteRecord::query()->whereYear('date', $year)->whereMonth('date', $month)->exists()
                || WasteTransportation::query()->whereYear('transportation_date', $year)->whereMonth('transportation_date', $month)->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     categories: array<string, \App\Models\WasteCategory>,
     *     characteristics: array<string, \App\Models\WasteCharacteristic>,
     *     waste_types: array<string, \App\Models\WasteType>,
     *     vendors: array<string, \App\Models\Vendor>
     * }
     */
    protected function seedMasterData(User $supervisor): array
    {
        $categories = [
            'hazardous' => WasteCategory::query()->updateOrCreate(
                ['code' => 'B3'],
                [
                    'name' => 'Limbah B3',
                    'description' => 'Limbah bahan berbahaya dan beracun.',
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
            'recyclable' => WasteCategory::query()->updateOrCreate(
                ['code' => 'REC'],
                [
                    'name' => 'Limbah Daur Ulang',
                    'description' => 'Limbah yang masih memiliki nilai daur ulang.',
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
            'general' => WasteCategory::query()->updateOrCreate(
                ['code' => 'GEN'],
                [
                    'name' => 'Limbah Umum',
                    'description' => 'Limbah umum non-B3 untuk operasional harian.',
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
        ];

        $characteristics = [
            'toxic' => WasteCharacteristic::query()->updateOrCreate(
                ['code' => 'TOX'],
                [
                    'name' => 'Toksik',
                    'description' => 'Karakteristik limbah B3 yang bersifat toksik.',
                    'is_hazardous' => true,
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
            'corrosive' => WasteCharacteristic::query()->updateOrCreate(
                ['code' => 'COR'],
                [
                    'name' => 'Korosif',
                    'description' => 'Karakteristik limbah yang bersifat korosif.',
                    'is_hazardous' => true,
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
            'non_hazardous' => WasteCharacteristic::query()->updateOrCreate(
                ['code' => 'NON'],
                [
                    'name' => 'Non B3',
                    'description' => 'Karakteristik limbah non-B3.',
                    'is_hazardous' => false,
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
        ];

        $wasteTypes = [
            'sludge' => WasteType::query()->updateOrCreate(
                ['code' => 'B3-SLD'],
                [
                    'name' => 'Sludge IPAL',
                    'category_id' => $categories['hazardous']->id,
                    'characteristic_id' => $characteristics['toxic']->id,
                    'description' => 'Sludge hasil pengolahan IPAL.',
                    'storage_period_days' => 90,
                    'transport_cost' => 150000,
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
            'contaminated_packaging' => WasteType::query()->updateOrCreate(
                ['code' => 'B3-KEM'],
                [
                    'name' => 'Kemasan Terkontaminasi',
                    'category_id' => $categories['hazardous']->id,
                    'characteristic_id' => $characteristics['corrosive']->id,
                    'description' => 'Kemasan bekas bahan kimia terkontaminasi.',
                    'storage_period_days' => 30,
                    'transport_cost' => 125000,
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
            'plastic' => WasteType::query()->updateOrCreate(
                ['code' => 'REC-PLA'],
                [
                    'name' => 'Plastik Campuran',
                    'category_id' => $categories['recyclable']->id,
                    'characteristic_id' => $characteristics['non_hazardous']->id,
                    'description' => 'Plastik campuran hasil segregasi.',
                    'storage_period_days' => 45,
                    'transport_cost' => 50000,
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
            'paper' => WasteType::query()->updateOrCreate(
                ['code' => 'REC-PAP'],
                [
                    'name' => 'Kertas dan Karton',
                    'category_id' => $categories['general']->id,
                    'characteristic_id' => $characteristics['non_hazardous']->id,
                    'description' => 'Kertas dan karton operasional kantor.',
                    'storage_period_days' => 60,
                    'transport_cost' => 35000,
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
        ];

        $vendors = [
            'waste_processor' => Vendor::query()->updateOrCreate(
                ['code' => 'WM-VEND-001'],
                [
                    'name' => 'PT Pengolah Limbah Aman',
                    'description' => 'Vendor pengangkutan dan pengolahan limbah B3.',
                    'contact_person' => 'Nadia Putri',
                    'phone' => '0811111000',
                    'email' => 'vendor.limbah@local.test',
                    'address' => 'Kawasan Industri A',
                    'license_number' => 'LIC-WM-001',
                    'license_expiry_date' => '2027-12-31',
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
            'recycler' => Vendor::query()->updateOrCreate(
                ['code' => 'WM-VEND-002'],
                [
                    'name' => 'CV Daur Ulang Mandiri',
                    'description' => 'Vendor pengangkutan limbah daur ulang.',
                    'contact_person' => 'Bagus Saputra',
                    'phone' => '0822222000',
                    'email' => 'vendor.recycle@local.test',
                    'address' => 'Sentra Daur Ulang',
                    'license_number' => 'LIC-WM-002',
                    'license_expiry_date' => '2027-12-31',
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
            'logistics' => Vendor::query()->updateOrCreate(
                ['code' => 'WM-VEND-003'],
                [
                    'name' => 'PT Logistik Hijau',
                    'description' => 'Vendor logistik limbah umum dan B3.',
                    'contact_person' => 'Sinta Maharani',
                    'phone' => '0833333000',
                    'email' => 'vendor.logistik@local.test',
                    'address' => 'Hub Logistik Timur',
                    'license_number' => 'LIC-WM-003',
                    'license_expiry_date' => '2027-12-31',
                    'is_active' => true,
                    'created_by' => $supervisor->id,
                    'updated_by' => $supervisor->id,
                ]
            ),
        ];

        return [
            'categories' => $categories,
            'characteristics' => $characteristics,
            'waste_types' => $wasteTypes,
            'vendors' => $vendors,
        ];
    }

    /**
     * @param  list<CarbonImmutable>  $periods
     * @param  array<string, \App\Models\WasteType>  $wasteTypes
     * @param  array<string, \App\Models\Vendor>  $vendors
     * @return array{waste_records_count: int, transportations_count: int}
     */
    protected function seedWasteRecordsAndTransportations(
        array $periods,
        User $operator,
        User $supervisor,
        array $wasteTypes,
        array $vendors,
        string $tenantCode,
    ): array {
        $recordCount = 0;
        $transportationCount = 0;
        $sourceOptions = [
            'Gudang B3',
            'Workshop Mekanik',
            'Area Produksi',
            'Kantor Administrasi',
            'TPS Limbah',
            'Area Utility',
        ];
        $statusPattern = [
            'draft',
            'draft',
            'draft',
            'pending_review',
            'pending_review',
            'pending_review',
            'approved',
            'approved',
            'approved',
            'approved',
            'rejected',
            'rejected',
        ];
        $typeKeys = array_keys($wasteTypes);

        foreach ($periods as $periodIndex => $period) {
            $monthlyApprovedRecords = [];
            $baseQuantities = $this->baseQuantitiesForMonth($periodIndex);

            foreach ($statusPattern as $index => $status) {
                $wasteType = $wasteTypes[$typeKeys[$index % count($typeKeys)]];
                $quantity = $baseQuantities[$index];
                $date = $period->setDay(min(26, 2 + ($index * 2)));

                $record = WasteRecord::query()->create([
                    'record_number' => $this->makeRecordNumber($tenantCode, $period, $index + 1),
                    'date' => $date->toDateString(),
                    'waste_type_id' => $wasteType->id,
                    'quantity' => $quantity,
                    'unit' => 'kg',
                    'source' => $sourceOptions[$index % count($sourceOptions)],
                    'description' => 'Data demo '.$wasteType->name.' untuk periode '.$period->format('F Y'),
                    'notes' => 'Catatan demo limbah umum.',
                    'status' => $status,
                    'rejection_reason' => $status === 'rejected' ? 'Dokumen manifest perlu dilengkapi.' : null,
                    'submitted_by' => in_array($status, ['pending_review', 'approved', 'rejected'], true) ? $operator->id : null,
                    'submitted_at' => in_array($status, ['pending_review', 'approved', 'rejected'], true) ? $date->setTime(10, 0) : null,
                    'approved_by' => in_array($status, ['approved', 'rejected'], true) ? $supervisor->id : null,
                    'approved_at' => $status === 'approved' ? $date->setTime(15, 0) : null,
                    'approval_notes' => $status === 'approved' ? 'Data limbah lengkap dan siap diangkut.' : null,
                    'created_by' => $operator->id,
                    'updated_by' => $operator->id,
                ]);

                if ($status === 'approved') {
                    $monthlyApprovedRecords[] = $record->fresh();
                }

                $recordCount++;
            }

            $transportationBlueprints = [
                ['status' => 'delivered', 'ratio' => 0.55, 'vendor' => 'waste_processor', 'vehicle' => 'B 9101 WM', 'driver' => 'Asep Gunawan', 'phone' => '0812000001'],
                ['status' => 'in_transit', 'ratio' => 0.45, 'vendor' => 'logistics', 'vehicle' => 'B 9102 WM', 'driver' => 'Rudi Hartono', 'phone' => '0812000002'],
                ['status' => 'pending', 'ratio' => 0.40, 'vendor' => 'recycler', 'vehicle' => 'B 9103 WM', 'driver' => 'Lina Sari', 'phone' => '0812000003'],
                ['status' => 'delivered', 'ratio' => 0.35, 'vendor' => 'waste_processor', 'vehicle' => 'B 9104 WM', 'driver' => 'Yusuf Maulana', 'phone' => '0812000004'],
                ['status' => 'cancelled', 'ratio' => 0.20, 'vendor' => 'logistics', 'vehicle' => 'B 9105 WM', 'driver' => 'Deden Kurnia', 'phone' => '0812000005'],
            ];

            foreach ($transportationBlueprints as $index => $blueprint) {
                $record = $monthlyApprovedRecords[$index % count($monthlyApprovedRecords)];
                $quantity = round((float) $record->quantity * $blueprint['ratio'], 2);
                $transportDate = $record->date->addDays($index + 1);

                $dispatchedAt = in_array($blueprint['status'], ['in_transit', 'delivered'], true)
                    ? $transportDate->copy()->setTime(8, 30)
                    : null;
                $deliveredAt = $blueprint['status'] === 'delivered'
                    ? $transportDate->copy()->setTime(15, 15)
                    : null;

                WasteTransportation::query()->create([
                    'transportation_number' => $this->makeTransportationNumber($tenantCode, $period, $index + 1),
                    'waste_record_id' => $record->id,
                    'vendor_id' => $vendors[$blueprint['vendor']]->id,
                    'transportation_date' => $transportDate->toDateString(),
                    'quantity' => $quantity,
                    'unit' => 'kg',
                    'vehicle_number' => $blueprint['vehicle'],
                    'driver_name' => $blueprint['driver'],
                    'driver_phone' => $blueprint['phone'],
                    'status' => $blueprint['status'],
                    'notes' => 'Transportasi demo '.$blueprint['status'].' untuk '.$record->record_number,
                    'delivery_notes' => $blueprint['status'] === 'delivered' ? 'Barang diterima lengkap di tujuan.' : null,
                    'dispatched_at' => $dispatchedAt,
                    'delivered_at' => $deliveredAt,
                    'created_by' => $operator->id,
                    'updated_by' => $operator->id,
                ]);

                $transportationCount++;
            }
        }

        return [
            'waste_records_count' => $recordCount,
            'transportations_count' => $transportationCount,
        ];
    }

    /**
     * @return list<float>
     */
    protected function baseQuantitiesForMonth(int $periodIndex): array
    {
        $progressiveOffset = (int) floor($periodIndex / 3) * 8;
        $baseline = [175, 165, 155, 145, 135, 125, 0, 0, 0, 0, 118, 108];

        return match ($periodIndex % 3) {
            0 => [
                ...array_slice($baseline, 0, 6),
                220 + $progressiveOffset,
                150 + $progressiveOffset,
                260 + $progressiveOffset,
                240 + $progressiveOffset,
                ...array_slice($baseline, 10),
            ],
            1 => [
                ...array_slice($baseline, 0, 6),
                380 + $progressiveOffset,
                160 + $progressiveOffset,
                180 + $progressiveOffset,
                170 + $progressiveOffset,
                ...array_slice($baseline, 10),
            ],
            default => [
                ...array_slice($baseline, 0, 6),
                180 + $progressiveOffset,
                390 + $progressiveOffset,
                170 + $progressiveOffset,
                160 + $progressiveOffset,
                ...array_slice($baseline, 10),
            ],
        };
    }

    protected function makeRecordNumber(string $tenantCode, CarbonImmutable $period, int $sequence): string
    {
        return 'WR-'.$tenantCode.'-'.$period->format('Y-m').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    protected function makeTransportationNumber(string $tenantCode, CarbonImmutable $period, int $sequence): string
    {
        return 'TR-'.$tenantCode.'-'.$period->format('Y-m').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
