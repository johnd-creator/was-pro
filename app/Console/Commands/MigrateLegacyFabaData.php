<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\FabaLegacyMigrationService;
use App\Services\TenantService;
use Illuminate\Console\Command;
use Throwable;

class MigrateLegacyFabaData extends Command
{
    protected $signature = 'faba:migrate-legacy
        {--schema= : Nama schema tenant yang akan dimigrasikan}
        {--tenant= : Kode organisasi tenant yang akan dimigrasikan}
        {--dry-run : Tampilkan simulasi tanpa menulis data}';

    protected $description = 'Migrasikan data legacy FABA production/utilization entries ke faba_movements';

    public function __construct(
        protected TenantService $tenantService,
        protected FabaLegacyMigrationService $fabaLegacyMigrationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $schemaName = $this->resolveSchemaName();

        if ($schemaName === null) {
            $this->error('Schema tenant tidak ditemukan. Gunakan --schema atau --tenant.');

            return self::FAILURE;
        }

        if (! $this->tenantService->schemaExists($schemaName)) {
            $this->error(sprintf('Schema [%s] tidak ditemukan.', $schemaName));

            return self::FAILURE;
        }

        try {
            $this->tenantService->switchToSchema($schemaName);
            $summary = $this->fabaLegacyMigrationService->migrate((bool) $this->option('dry-run'));
        } catch (Throwable $exception) {
            $this->tenantService->switchToPublic();
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->tenantService->switchToPublic();

        $this->info(sprintf(
            'Migrasi legacy FABA untuk schema [%s] %s.',
            $schemaName,
            $summary['dry_run'] ? '(dry-run)' : 'selesai'
        ));
        $this->newLine();
        $this->renderSection('Produksi', $summary['production']);
        $this->newLine();
        $this->renderSection('Pemanfaatan', $summary['utilization']);

        return self::SUCCESS;
    }

    protected function resolveSchemaName(): ?string
    {
        $schemaName = $this->option('schema');

        if (is_string($schemaName) && $schemaName !== '') {
            return $schemaName;
        }

        $tenantCode = $this->option('tenant');

        if (is_string($tenantCode) && $tenantCode !== '') {
            return Organization::query()
                ->where('code', $tenantCode)
                ->value('schema_name');
        }

        $currentSchema = $this->tenantService->getCurrentSchema();

        return $currentSchema !== 'public' ? $currentSchema : null;
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    protected function renderSection(string $label, array $summary): void
    {
        $this->line($label.':');
        $this->line('- Sumber: '.(int) $summary['source_count']);
        $this->line('- Termigrasi baru: '.(int) $summary['migrated_count']);
        $this->line('- Sudah pernah dimigrasikan: '.(int) $summary['already_migrated_count']);
        $this->line('- Tidak termapping: '.(int) $summary['unmapped_count']);

        if (is_string($summary['message']) && $summary['message'] !== '') {
            $this->comment('  '.$summary['message']);
        }
    }
}
