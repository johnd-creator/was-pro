<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FabaAuditLog extends Model
{
    use HasFactory, HasUuids;

    public const MODULE_PRODUCTION = 'production';

    public const MODULE_UTILIZATION = 'utilization';

    public const MODULE_APPROVAL = 'approval';

    public const MODULE_BALANCE = 'opening_balance';

    public const MODULE_MOVEMENT = 'movement';

    public const MODULE_ADJUSTMENT = 'adjustment';

    public const MODULE_SNAPSHOT = 'closing_snapshot';

    protected $fillable = [
        'actor_id',
        'action',
        'module',
        'reference_type',
        'reference_id',
        'year',
        'month',
        'summary',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'details' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }
}
