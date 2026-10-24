<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

class DocumentNumberService
{
    /** @param class-string<Model> $model */
    public function next(string $model, string $column, string $prefix): string
    {
        if (DB::connection()->transactionLevel() === 0) {
            throw new LogicException('Document numbering requires an operational transaction.');
        }
        app(OperationalTransactionService::class)->run(fn (): null => null);
        $maximum = 0;
        foreach ($model::withTrashed()->where($column, 'like', $prefix.'-%')->pluck($column) as $number) {
            if (preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/D', $number, $matches)) {
                $maximum = max($maximum, (int) $matches[1]);
            }
        }

        return $prefix.'-'.str_pad((string) ($maximum + 1), 4, '0', STR_PAD_LEFT);
    }
}
