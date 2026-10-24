<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

class OperationalTransactionService
{
    public function run(Closure $operation): mixed
    {
        return DB::connection()->transaction(function () use ($operation): mixed {
            // The physical schema is the lock boundary; caller-supplied tenant IDs are never used.
            DB::connection()->select("SELECT pg_advisory_xact_lock(hashtextextended(current_database() || ':' || current_schema() || ':operations', 0))");

            return $operation();
        });
    }
}
