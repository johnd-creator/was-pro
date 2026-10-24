<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class TenantService
{
    /**
     * @var list<string>
     */
    protected array $protectedSchemas = [
        'tenant_twms_demo',
    ];

    /**
     * The current schema name.
     */
    protected ?string $currentSchema = null;

    public function provisionOrganization(array $attributes): Organization
    {
        return $this->withRestoredSchema(function () use ($attributes): Organization {
            return DB::connection()->transaction(function () use ($attributes): Organization {
                $this->switchToPublic();
                $schemaName = 'org_'.str_replace('-', '', (string) Str::uuid());

                if ($this->schemaExists($schemaName)) {
                    throw new RuntimeException('Refusing to provision an existing tenant schema.');
                }

                $organization = Organization::create([...$attributes, 'schema_name' => $schemaName]);

                if (! $this->createSchema($schemaName, mustBeNew: true)) {
                    throw new RuntimeException('Tenant schema creation failed.');
                }

                $this->runMigrationsForTenant($schemaName, 'database/migrations/tenant');

                return $organization;
            });
        });
    }

    /**
     * Create a new schema for an organization.
     */
    public function createSchema(string $schemaName, bool $mustBeNew = false): bool
    {
        try {
            // Check if schema already exists
            if (! $mustBeNew && $this->schemaExists($schemaName)) {
                return true;
            }

            DB::statement("CREATE SCHEMA \"{$schemaName}\"");

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to create schema: {$schemaName}", ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Drop a schema.
     */
    public function dropSchema(string $schemaName): bool
    {
        $this->guardProtectedSchemaDrop($schemaName);

        try {
            // Drop all tables in the schema first
            $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = '{$schemaName}'");

            foreach ($tables as $table) {
                Schema::dropIfExists($schemaName.'.'.$table->tablename);
            }

            DB::statement("DROP SCHEMA IF EXISTS \"{$schemaName}\" CASCADE");

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to drop schema: {$schemaName}", ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function usesDedicatedTestingDatabase(): bool
    {
        return str_ends_with($this->currentDatabaseName(), '_test');
    }

    /**
     * Switch to a specific schema.
     */
    public function switchToSchema(string $schemaName): void
    {
        // Validate schema exists
        if (! $this->schemaExists($schemaName)) {
            throw new \Exception("Schema {$schemaName} does not exist");
        }

        $this->currentSchema = $schemaName;
        // Include both tenant schema and public schema in search path
        // This allows Laravel to find system tables (sessions, cache, etc.) in public schema
        DB::statement("SET search_path TO \"{$schemaName}\", public");
    }

    /**
     * Switch to the public schema.
     */
    public function switchToPublic(): void
    {
        $this->currentSchema = 'public';
        DB::statement('SET search_path TO public');
    }

    /**
     * Get the current schema name.
     */
    public function getCurrentSchema(): ?string
    {
        return $this->currentSchema;
    }

    /**
     * Check if a schema exists.
     */
    public function schemaExists(string $schemaName): bool
    {
        $originalSchema = $this->currentSchema;

        try {
            // Reset to public schema first to avoid transaction issues
            DB::statement('SET search_path TO public');

            $result = DB::select('SELECT schema_name FROM information_schema.schemata WHERE schema_name = ?', [$schemaName]);

            return count($result) > 0;
        } catch (\Exception $e) {
            // If we're in a failed transaction, assume schema doesn't exist
            \Log::warning("Failed to check schema existence: {$schemaName}", ['error' => $e->getMessage()]);

            return false;
        } finally {
            if ($originalSchema && $originalSchema !== 'public') {
                DB::statement("SET search_path TO \"{$originalSchema}\", public");
            } else {
                DB::statement('SET search_path TO public');
            }
        }
    }

    /**
     * Get all tables in a schema.
     */
    public function getSchemaTables(string $schemaName): array
    {
        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = '{$schemaName}'");

        return array_column($tables, 'tablename');
    }

    /**
     * Run migrations for a specific tenant schema.
     */
    public function runMigrationsForTenant(string $schemaName, string $migrationPath): void
    {
        $this->withRestoredSchema(function () use ($schemaName, $migrationPath): void {
            $connectionName = config('database.default');

            DB::connection($connectionName)->transaction(function () use ($schemaName, $migrationPath, $connectionName): void {
                if (! $this->schemaExists($schemaName)) {
                    throw new RuntimeException('Tenant schema does not exist.');
                }

                $this->setConnectionSearchPath($connectionName, "\"{$schemaName}\", public", $schemaName);
                $exitCode = \Artisan::call('migrate', [
                    '--path' => $migrationPath,
                    '--force' => true,
                    '--database' => $connectionName,
                ]);

                if ($exitCode !== 0) {
                    throw new RuntimeException('Tenant migration command failed.');
                }
            });
        });
    }

    protected function withRestoredSchema(callable $callback): mixed
    {
        $originalSchema = $this->getCurrentSchema();
        $connectionName = config('database.default');
        $connection = DB::connection($connectionName);
        $actualSearchPath = $connection->selectOne('SHOW search_path', [], false)->search_path;
        $originalSearchPath = config("database.connections.{$connectionName}.search_path");
        $originalConnectionSchema = config("database.connections.{$connectionName}.schema");

        try {
            return $callback();
        } finally {
            config([
                "database.connections.{$connectionName}.search_path" => $originalSearchPath,
                "database.connections.{$connectionName}.schema" => $originalConnectionSchema,
            ]);

            $connection->statement("SELECT set_config('search_path', ?, false)", [$actualSearchPath]);
            $this->currentSchema = $originalSchema;
        }
    }

    protected function setConnectionSearchPath(string $connectionName, string $searchPath, string $schema): void
    {
        $connection = DB::connection($connectionName);

        config([
            "database.connections.{$connectionName}.search_path" => $searchPath,
            "database.connections.{$connectionName}.schema" => $schema,
        ]);

        if ($connection->transactionLevel() === 0) {
            DB::purge($connectionName);
            DB::reconnect($connectionName);
        }

        DB::statement("SET search_path TO {$searchPath}");

        $this->currentSchema = $schema;
    }

    protected function guardProtectedSchemaDrop(string $schemaName): void
    {
        if (! $this->isProtectedSchema($schemaName) || $this->usesDedicatedTestingDatabase()) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Refusing to drop protected schema [%s] while using database [%s]. Use the dedicated *_test database for destructive verification.',
            $schemaName,
            $this->currentDatabaseName(),
        ));
    }

    protected function isProtectedSchema(string $schemaName): bool
    {
        return in_array($schemaName, $this->protectedSchemas, true);
    }

    protected function currentDatabaseName(): string
    {
        $connectionName = (string) config('database.default');

        return (string) config("database.connections.{$connectionName}.database");
    }
}
