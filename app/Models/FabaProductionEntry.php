<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FabaProductionEntry extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const DEFAULT_UNIT = 'ton';

    public const MATERIAL_FLY_ASH = 'fly_ash';

    public const MATERIAL_BOTTOM_ASH = 'bottom_ash';

    public const TYPE_PRODUCTION = 'production';

    public const TYPE_POK = 'pok';

    public const TYPE_WORKSHOP = 'workshop';

    public const TYPE_REJECT = 'reject';

    public const ENTRY_TYPES_BY_MATERIAL = [
        self::MATERIAL_FLY_ASH => [
            self::TYPE_PRODUCTION,
            self::TYPE_POK,
            self::TYPE_WORKSHOP,
            self::TYPE_REJECT,
        ],
        self::MATERIAL_BOTTOM_ASH => [
            self::TYPE_PRODUCTION,
            self::TYPE_WORKSHOP,
            self::TYPE_REJECT,
        ],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'entry_number',
        'transaction_date',
        'material_type',
        'entry_type',
        'quantity',
        'unit',
        'note',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'quantity' => 'decimal:2',
        ];
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month);
    }

    public static function materialOptions(): array
    {
        return [
            self::MATERIAL_FLY_ASH,
            self::MATERIAL_BOTTOM_ASH,
        ];
    }

    public static function entryTypeOptions(): array
    {
        return [
            self::TYPE_PRODUCTION,
            self::TYPE_POK,
            self::TYPE_WORKSHOP,
            self::TYPE_REJECT,
        ];
    }

    public static function entryTypeOptionsByMaterial(): array
    {
        return self::ENTRY_TYPES_BY_MATERIAL;
    }

    public static function isValidEntryTypeForMaterial(string $materialType, string $entryType): bool
    {
        return in_array($entryType, self::ENTRY_TYPES_BY_MATERIAL[$materialType] ?? [], true);
    }
}
