<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FabaUtilizationEntry extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const DEFAULT_UNIT = 'ton';

    public const MATERIAL_FLY_ASH = 'fly_ash';

    public const MATERIAL_BOTTOM_ASH = 'bottom_ash';

    public const TYPE_EXTERNAL = 'external';

    public const TYPE_INTERNAL = 'internal';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'entry_number',
        'transaction_date',
        'material_type',
        'utilization_type',
        'vendor_id',
        'quantity',
        'unit',
        'document_number',
        'document_date',
        'attachment_path',
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
            'document_date' => 'date',
            'quantity' => 'decimal:2',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
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

    public static function utilizationTypeOptions(): array
    {
        return [
            self::TYPE_EXTERNAL,
            self::TYPE_INTERNAL,
        ];
    }
}
