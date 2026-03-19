<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\TenantService;
use Illuminate\Console\Command;

class MigrateTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:tenant
        {--org= : The organization ID or code to migrate}
        {--all : Migrate all tenant schemas}
        {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations for tenant schemas';

    /**
     * Create a new command instance.
     */
    public function __construct(
        protected TenantService $tenantService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->migrateAllTenants();
        }

        $orgIdentifier = $this->option('org');

        if (! $orgIdentifier) {
            $this->error('Please provide an organization ID or code using the --org option, or use --all to migrate all tenants.');

            return self::FAILURE;
        }

        return $this->migrateSingleTenant($orgIdentifier);
    }

    /**
     * Migrate a single tenant.
     */
    protected function migrateSingleTenant(string $orgIdentifier): int
    {
        // Find organization by code (since ID is UUID)
        $organization = Organization::where('code', $orgIdentifier)->first();

        if (! $organization) {
            $this->error("Organization not found: {$orgIdentifier}");

            return self::FAILURE;
        }

        $this->info("Migrating tenant schema: {$organization->schema_name} ({$organization->name})");

        // Ensure schema exists
        if (! $this->tenantService->schemaExists($organization->schema_name)) {
            $this->info("Creating schema: {$organization->schema_name}");
            $this->tenantService->createSchema($organization->schema_name);
        }

        // Switch to tenant schema
        $this->tenantService->switchToSchema($organization->schema_name);

        // Run migrations
        $this->call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--force' => $this->option('force'),
        ]);

        $this->info("Tenant schema {$organization->schema_name} migrated successfully.");

        // Switch back to public schema
        $this->tenantService->switchToPublic();

        return self::SUCCESS;
    }

    /**
     * Migrate all tenant schemas.
     */
    protected function migrateAllTenants(): int
    {
        $organizations = Organization::active()->get();

        if ($organizations->isEmpty()) {
            $this->warn('No organizations found to migrate.');

            return self::SUCCESS;
        }

        $this->info("Migrating {$organizations->count()} tenant schemas...");

        foreach ($organizations as $organization) {
            $this->info("Migrating: {$organization->name} ({$organization->schema_name})");

            // Ensure schema exists
            if (! $this->tenantService->schemaExists($organization->schema_name)) {
                $this->tenantService->createSchema($organization->schema_name);
            }

            // Switch to tenant schema
            $this->tenantService->switchToSchema($organization->schema_name);

            // Run migrations
            $this->call('migrate', [
                '--path' => 'database/migrations/tenant',
                '--force' => $this->option('force'),
            ]);
        }

        $this->info('All tenant schemas migrated successfully.');

        // Switch back to public schema
        $this->tenantService->switchToPublic();

        return self::SUCCESS;
    }
}
