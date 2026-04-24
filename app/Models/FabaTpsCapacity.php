<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FabaTpsCapacity extends Model
{
    use HasFactory, HasUuids;

    public const DEFAULT_WARNING_THRESHOLD = 80.0;

    public const DEFAULT_CRITICAL_THRESHOLD = 95.0;

    public $timestamps = false;

    protected $fillable = [
        'material_type',
        'capacity',
        'warning_threshold',
        'critical_threshold',
        'updated_by',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:2',
            'warning_threshold' => 'decimal:2',
            'critical_threshold' => 'decimal:2',
            'updated_at' => 'datetime',
        ];
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
