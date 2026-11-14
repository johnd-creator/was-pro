<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class WasteSource extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function available(): bool
    {
        return Schema::hasTable('waste_sources') && Schema::hasColumn('waste_records', 'source_id');
    }

    public static function options(?string $selected = null): array
    {
        if (! static::available()) {
            return [];
        }

        return static::query()->where(fn ($q) => $q->where('is_active', true)->when($selected, fn ($q) => $q->orWhere('id', $selected)))->orderBy('name')->get(['id', 'name', 'is_active'])->toArray();
    }
}
