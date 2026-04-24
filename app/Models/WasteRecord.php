<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WasteRecord extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($record) {
            $record->calculateExpiryDate();
        });

        static::updating(function ($record) {
            if ($record->isDirty('date') || $record->isDirty('waste_type_id')) {
                $record->calculateExpiryDate();
            }
        });
    }

    /**
     * Calculate expiry date based on waste type storage period.
     */
    public function calculateExpiryDate(): void
    {
        if (! $this->waste_type_id || ! $this->date) {
            return;
        }

        $wasteType = WasteType::find($this->waste_type_id);
        if ($wasteType && $wasteType->storage_period_days > 0) {
            $this->expiry_date = $this->date->addDays($wasteType->storage_period_days);
        }
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'record_number',
        'date',
        'waste_type_id',
        'quantity',
        'unit',
        'source',
        'description',
        'notes',
        'status',
        'rejection_reason',
        'expiry_date',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'approval_notes',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'expiry_date' => 'date',
            'quantity' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Get the waste type for the waste record.
     */
    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }

    /**
     * Get the user who submitted the record.
     */
    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the user who created the record.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved the record.
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the hauling requests for the waste record.
     */
    public function haulings(): HasMany
    {
        return $this->hasMany(WasteHauling::class, 'waste_record_id');
    }

    /**
     * Get approved hauling requests for the waste record.
     */
    public function approvedHaulings(): HasMany
    {
        return $this->haulings()->where('status', 'approved');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by submitted user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope to filter pending approval records.
     */
    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_review');
    }

    /**
     * Scope to filter approved records.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to filter draft records.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope to filter expired records.
     */
    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now()->toDateString());
    }

    /**
     * Scope to filter expiring soon records (within next X days).
     */
    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->whereBetween('expiry_date', [
            now()->toDateString(),
            now()->addDays($days)->toDateString(),
        ]);
    }

    /**
     * Scope to filter not expired records.
     */
    public function scopeNotExpired($query)
    {
        return $query->where('expiry_date', '>=', now()->toDateString());
    }

    /**
     * Check if the record is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Check if the record is pending review.
     */
    public function isPendingReview(): bool
    {
        return $this->status === 'pending_review';
    }

    /**
     * Check if the record is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the record is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if the record can be edited.
     */
    public function canBeEdited(): bool
    {
        return $this->isDraft() || $this->isRejected();
    }

    /**
     * Check if the record can be submitted.
     */
    public function canBeSubmitted(): bool
    {
        return $this->isDraft() || $this->isRejected();
    }

    /**
     * Check if the record can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->isPendingReview();
    }

    /**
     * Check if the record can be rejected.
     */
    public function canBeRejected(): bool
    {
        return $this->isPendingReview();
    }

    /**
     * Check if the record is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    /**
     * Check if the record is expiring soon (within X days).
     */
    public function isExpiringSoon(int $days = 7): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isBetween(
            now()->startOfDay(),
            now()->addDays($days)->endOfDay()
        );
    }

    /**
     * Get the expiry status (expired, expiring_soon, or fresh).
     */
    public function getExpiryStatus(): string
    {
        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->isExpiringSoon()) {
            return 'expiring_soon';
        }

        return 'fresh';
    }

    /**
     * Get the expiry status badge class.
     */
    public function getExpiryBadgeClass(): string
    {
        return match ($this->getExpiryStatus()) {
            'expired' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            'expiring_soon' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
            'fresh' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        };
    }

    /**
     * Get the expiry status label.
     */
    public function getExpiryLabel(): string
    {
        if (! $this->expiry_date) {
            return 'N/A';
        }

        return match ($this->getExpiryStatus()) {
            'expired' => 'Expired',
            'expiring_soon' => 'Expiring Soon ('.$this->expiry_date->diffInDays(now()).' days)',
            'fresh' => 'Fresh ('.$this->expiry_date->diffInDays(now()).' days)',
            default => 'N/A',
        };
    }

    /**
     * Get the status badge class.
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
            'pending_review' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'approved' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        };
    }

    /**
     * Get the status label.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'pending_review' => 'Pending Review',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get total approved hauled quantity.
     */
    public function getApprovedHauledQuantity(): float
    {
        if (array_key_exists('approved_hauled_quantity', $this->attributes)) {
            return (float) $this->attributes['approved_hauled_quantity'];
        }

        if ($this->relationLoaded('haulings')) {
            return (float) $this->haulings
                ->where('status', 'approved')
                ->sum('quantity');
        }

        return (float) $this->approvedHaulings()->sum('quantity');
    }

    /**
     * Get remaining quantity that has not been hauled.
     */
    public function getRemainingQuantity(): float
    {
        return max(0, (float) $this->quantity - $this->getApprovedHauledQuantity());
    }

    /**
     * Get quantity reserved by approved and pending hauling requests.
     */
    public function getReservedHaulingQuantity(?string $ignoreHaulingId = null): float
    {
        if ($this->relationLoaded('haulings')) {
            return (float) $this->haulings
                ->filter(function (WasteHauling $hauling) use ($ignoreHaulingId): bool {
                    if ($ignoreHaulingId && (string) $hauling->id === $ignoreHaulingId) {
                        return false;
                    }

                    return in_array($hauling->status, ['pending_approval', 'approved'], true);
                })
                ->sum('quantity');
        }

        return (float) $this->haulings()
            ->whereIn('status', ['pending_approval', 'approved'])
            ->when($ignoreHaulingId, fn ($query) => $query->where('id', '!=', $ignoreHaulingId))
            ->sum('quantity');
    }

    /**
     * Get derived operational status for hauling.
     */
    public function getOperationalStatus(): string
    {
        $hauledQuantity = $this->getApprovedHauledQuantity();

        if ($hauledQuantity <= 0) {
            return 'not_hauled';
        }

        if ($this->getRemainingQuantity() <= 0) {
            return 'completed';
        }

        return 'partially_hauled';
    }

    /**
     * Get derived operational status label for hauling.
     */
    public function getOperationalStatusLabel(): string
    {
        return match ($this->getOperationalStatus()) {
            'not_hauled' => 'Belum Diangkut',
            'partially_hauled' => 'Sebagian Diangkut',
            'completed' => 'Selesai',
            default => 'Belum Diangkut',
        };
    }

    /**
     * Workflow: Submit record for approval.
     */
    public function submitForApproval(string $submittedBy): bool
    {
        if (! $this->canBeSubmitted()) {
            return false;
        }

        $this->status = 'pending_review';
        $this->submitted_by = $submittedBy;
        $this->submitted_at = now();

        return $this->save();
    }

    /**
     * Workflow: Approve the record.
     */
    public function approve(string $approvedBy, ?string $notes = null): bool
    {
        if (! $this->canBeApproved()) {
            return false;
        }

        $this->status = 'approved';
        $this->approved_by = $approvedBy;
        $this->approved_at = now();
        $this->approval_notes = $notes;

        return $this->save();
    }

    /**
     * Workflow: Reject the record.
     */
    public function reject(string $rejectedBy, string $reason): bool
    {
        if (! $this->canBeRejected()) {
            return false;
        }

        $this->status = 'rejected';
        $this->rejection_reason = $reason;
        $this->approved_by = $rejectedBy;

        return $this->save();
    }

    /**
     * Workflow: Return rejected record to draft.
     */
    public function returnToDraft(): bool
    {
        if (! $this->isRejected()) {
            return false;
        }

        $this->status = 'draft';
        $this->rejection_reason = null;

        return $this->save();
    }
}
