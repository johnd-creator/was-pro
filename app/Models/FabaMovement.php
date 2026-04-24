<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FabaMovement extends Model
{
    use HasFactory, HasUuids;

    public const DEFAULT_UNIT = 'ton';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const MATERIAL_FLY_ASH = 'fly_ash';

    public const MATERIAL_BOTTOM_ASH = 'bottom_ash';

    public const STOCK_EFFECT_IN = 'in';

    public const STOCK_EFFECT_OUT = 'out';

    public const TYPE_OPENING_BALANCE = 'opening_balance';

    public const TYPE_PRODUCTION = 'production';

    public const TYPE_WORKSHOP = 'workshop';

    public const TYPE_UTILIZATION_EXTERNAL = 'utilization_external';

    public const TYPE_UTILIZATION_INTERNAL = 'utilization_internal';

    public const TYPE_REJECT = 'reject';

    public const TYPE_DISPOSAL_POK = 'disposal_pok';

    public const TYPE_ADJUSTMENT_IN = 'adjustment_in';

    public const TYPE_ADJUSTMENT_OUT = 'adjustment_out';

    protected $fillable = [
        'transaction_date',
        'material_type',
        'movement_type',
        'stock_effect',
        'quantity',
        'unit',
        'vendor_id',
        'internal_destination_id',
        'purpose_id',
        'document_number',
        'document_date',
        'attachment_path',
        'reference_type',
        'reference_id',
        'period_year',
        'period_month',
        'approval_status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_note',
        'created_by',
        'updated_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'document_date' => 'date',
            'quantity' => 'decimal:2',
            'period_year' => 'integer',
            'period_month' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function internalDestination(): BelongsTo
    {
        return $this->belongsTo(FabaInternalDestination::class, 'internal_destination_id');
    }

    public function purpose(): BelongsTo
    {
        return $this->belongsTo(FabaPurpose::class, 'purpose_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('period_year', $year)->where('period_month', $month);
    }

    public function scopeForMaterial($query, string $materialType)
    {
        return $query->where('material_type', $materialType);
    }

    public function scopeInflows($query)
    {
        return $query->where('stock_effect', self::STOCK_EFFECT_IN);
    }

    public function scopeOutflows($query)
    {
        return $query->where('stock_effect', self::STOCK_EFFECT_OUT);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', self::STATUS_PENDING_APPROVAL);
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status', self::STATUS_APPROVED);
    }

    public function scopeRejected($query)
    {
        return $query->where('approval_status', self::STATUS_REJECTED);
    }

    public static function movementTypes(): array
    {
        return [
            self::TYPE_OPENING_BALANCE,
            self::TYPE_PRODUCTION,
            self::TYPE_WORKSHOP,
            self::TYPE_UTILIZATION_EXTERNAL,
            self::TYPE_UTILIZATION_INTERNAL,
            self::TYPE_REJECT,
            self::TYPE_DISPOSAL_POK,
            self::TYPE_ADJUSTMENT_IN,
            self::TYPE_ADJUSTMENT_OUT,
        ];
    }

    public static function materialOptions(): array
    {
        return [
            self::MATERIAL_FLY_ASH,
            self::MATERIAL_BOTTOM_ASH,
        ];
    }

    public static function productionTypeOptions(): array
    {
        return [
            self::TYPE_PRODUCTION,
            self::TYPE_WORKSHOP,
            self::TYPE_REJECT,
            self::TYPE_DISPOSAL_POK,
        ];
    }

    public static function productionTypeOptionsByMaterial(): array
    {
        return [
            self::MATERIAL_FLY_ASH => self::productionTypeOptions(),
            self::MATERIAL_BOTTOM_ASH => self::productionTypeOptions(),
        ];
    }

    public static function utilizationTypeOptions(): array
    {
        return [
            self::TYPE_UTILIZATION_EXTERNAL,
            self::TYPE_UTILIZATION_INTERNAL,
        ];
    }

    public static function approvalStatusOptions(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }

    public static function isValidProductionTypeForMaterial(string $materialType, string $movementType): bool
    {
        return in_array($movementType, self::productionTypeOptionsByMaterial()[$materialType] ?? [], true);
    }

    public function canApprove(): bool
    {
        return $this->approval_status === self::STATUS_PENDING_APPROVAL;
    }

    public function canReject(): bool
    {
        return $this->approval_status === self::STATUS_PENDING_APPROVAL;
    }
}
