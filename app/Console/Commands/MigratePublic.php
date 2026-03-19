<?php

namespace App\Console\Commands;

use App\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePublic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:public
        {--force : Force the operation to run when in production}
        {--seed : Indicates if the seed task should be re-run}
        {--path=* : The path(s) to the migrations files to be executed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations for the public schema';

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
        // Switch to public schema
        $this->tenantService->switchToPublic();

        $this->info('Running migrations for public schema...');

        // Set the search path to public
        DB::statement('SET search_path TO public');

        // Run migrations
        $this->call('migrate', [
            '--force' => $this->option('force'),
        ]);

        if ($this->option('seed')) {
            $this->info('Running seeders for public schema...');
            $this->call('db:seed', [
                '--force' => $this->option('force'),
            ]);
        }

        $this->info('Public schema migrations completed successfully.');

        return self::SUCCESS;
    }
}
