<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FabaMonthlyClosingVersion extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'year' => 'integer', 'month' => 'integer', 'version' => 'integer',
            'approved_at' => 'datetime', 'created_at' => 'datetime',
            'snapshot_payload' => 'array', 'warning_summary' => 'array', 'provenance' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Arsip closing tidak dapat diubah.'));
        static::deleting(fn () => throw new \LogicException('Arsip closing tidak dapat dihapus.'));
    }
}
