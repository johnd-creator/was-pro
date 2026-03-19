<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WasteTransportation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($transportation) {
            // Generate transportation number if not set
            if (empty($transportation->transportation_number)) {
                $user = auth()->user();
                $organization = $user?->organization;

                // Fallback to waste record's organization if user org not available
                if (! $organization && $transportation->waste_record_id) {
                    $wasteRecord = WasteRecord::find($transportation->waste_record_id);
                    if ($wasteRecord) {
                        $organization = $wasteRecord->createdBy?->organization;
                    }
                }

                $orgCode = $organization?->code ?? 'UNKNOWN';
                $prefix = 'TR-'.$orgCode.'-'.now()->format('Y-m');

                $lastTransportation = self::where('transportation_number', 'like', $prefix.'%')
                    ->orderBy('transportation_number', 'desc')
                    ->first();

                $sequence = $lastTransportation
                    ? (int) substr($lastTransportation->transportation_number, -4) + 1
                    : 1;

                $transportation->transportation_number = $prefix.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transportation_number',
        'waste_record_id',
        'vendor_id',
        'transportation_date',
        'quantity',
        'unit',
        'vehicle_number',
        'driver_name',
        'driver_phone',
        'status',
        'notes',
        'delivery_notes',
        'dispatched_at',
        'delivered_at',
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
            'transportation_date' => 'date',
            'quantity' => 'decimal:2',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * Get the waste record for the transportation.
     */
    public function wasteRecord(): BelongsTo
    {
        return $this->belongsTo(WasteRecord::class);
    }

    /**
     * Get the vendor for the transportation.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get the user who created the transportation.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generate unique transportation number.
     * Format: TR-{ORG_CODE}-{YYYY}-{MM}-{SEQ}
     */
    public function generateTransportationNumber(): string
    {
        $user = auth()->user();
        $organization = $user?->organization;

        // Fallback to a default code if no organization
        $orgCode = $organization?->code ?? 'UNKNOWN';

        $prefix = 'TR-'.$orgCode.'-'.now()->format('Y-m');

        $lastTransportation = self::where('transportation_number', 'like', $prefix.'%')
            ->orderBy('transportation_number', 'desc')
            ->first();

        $sequence = $lastTransportation
            ? (int) substr($lastTransportation->transportation_number, -4) + 1
            : 1;

        return $prefix.'-'.str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter pending transportations.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to filter in-transit transportations.
     */
    public function scopeInTransit($query)
    {
        return $query->where('status', 'in_transit');
    }

    /**
     * Scope to filter delivered transportations.
     */
    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    /**
     * Scope to filter cancelled transportations.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    /**
     * Check if the quantity exceeds the waste record quantity.
     */
    public function quantityExceedsRecord(): bool
    {
        $wasteRecord = $this->wasteRecord;

        if (! $wasteRecord) {
            return false;
        }

        // Calculate total transported quantity for this waste record
        $totalTransported = self::where('waste_record_id', $this->waste_record_id)
            ->where('id', '!=', $this->id)
            ->where('status', '!=', 'cancelled')
            ->sum('quantity');

        return ($totalTransported + $this->quantity) > $wasteRecord->quantity;
    }

    /**
     * Get remaining quantity available for transportation.
     */
    public function getRemainingQuantity(): float
    {
        $wasteRecord = $this->wasteRecord;

        if (! $wasteRecord) {
            return 0;
        }

        $totalTransported = self::where('waste_record_id', $this->waste_record_id)
            ->where('id', '!=', $this->id)
            ->where('status', '!=', 'cancelled')
            ->sum('quantity');

        return max(0, $wasteRecord->quantity - $totalTransported);
    }

    /**
     * Check if the transportation can be dispatched.
     */
    public function canBeDispatched(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the transportation can be marked as delivered.
     */
    public function canBeDelivered(): bool
    {
        return $this->status === 'in_transit';
    }

    /**
     * Check if the transportation can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'in_transit']);
    }

    /**
     * Dispatch the transportation.
     */
    public function dispatch(): bool
    {
        if (! $this->canBeDispatched()) {
            return false;
        }

        $this->status = 'in_transit';
        $this->dispatched_at = now();

        return $this->save();
    }

    /**
     * Mark the transportation as delivered.
     */
    public function markAsDelivered(?string $deliveryNotes = null): bool
    {
        if (! $this->canBeDelivered()) {
            return false;
        }

        $this->status = 'delivered';
        $this->delivered_at = now();
        $this->delivery_notes = $deliveryNotes;

        return $this->save();
    }

    /**
     * Cancel the transportation.
     */
    public function cancel(): bool
    {
        if (! $this->canBeCancelled()) {
            return false;
        }

        $this->status = 'cancelled';

        return $this->save();
    }

    /**
     * Check if the record is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the record is in transit.
     */
    public function isInTransit(): bool
    {
        return $this->status === 'in_transit';
    }

    /**
     * Check if the record is delivered.
     */
    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Check if the record is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Get the status badge class.
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            'pending' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            'in_transit' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            'delivered' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
        };
    }

    /**
     * Get the status label.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'in_transit' => 'In Transit',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }
}
