<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantService
{
    /**
     * The current schema name.
     */
    protected ?string $currentSchema = null;

    /**
     * Create a new schema for an organization.
     */
    public function createSchema(string $schemaName): bool
    {
        try {
            // Check if schema already exists
            if ($this->schemaExists($schemaName)) {
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
        $originalSchema = $this->getCurrentSchema();
        $connectionName = config('database.default');
        $originalSearchPath = config("database.connections.{$connectionName}.search_path");
        $originalConnectionSchema = config("database.connections.{$connectionName}.schema");

        try {
            $this->setConnectionSearchPath($connectionName, "\"{$schemaName}\", public", $schemaName);

            // Run migrations using the artisan command
            \Artisan::call('migrate', [
                '--path' => $migrationPath,
                '--force' => true,
                '--database' => $connectionName,
            ]);
        } finally {
            $this->setConnectionSearchPath(
                $connectionName,
                is_string($originalSearchPath) ? $originalSearchPath : 'public',
                is_string($originalConnectionSchema) ? $originalConnectionSchema : 'public',
            );

            if ($originalSchema) {
                $this->switchToSchema($originalSchema);
            } else {
                $this->switchToPublic();
            }
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
}
