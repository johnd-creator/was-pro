<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WasteHauling extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $hauling): void {
            if (! $hauling->hauling_number) {
                $organization = auth()->user()?->organization;

                if (! $organization && $hauling->waste_record_id) {
                    $wasteRecord = WasteRecord::query()->with('createdBy.organization')->find($hauling->waste_record_id);
                    $organization = $wasteRecord?->createdBy?->organization;
                }

                $prefix = 'WH-'.($organization?->code ?? 'UNKNOWN').'-'.now()->format('Y-m');
                $lastHauling = self::query()
                    ->where('hauling_number', 'like', $prefix.'%')
                    ->orderByDesc('hauling_number')
                    ->first();

                $sequence = $lastHauling
                    ? (int) substr($lastHauling->hauling_number, -4) + 1
                    : 1;

                $hauling->hauling_number = $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    protected $fillable = [
        'hauling_number',
        'waste_record_id',
        'hauling_date',
        'quantity',
        'unit',
        'notes',
        'status',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejection_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'hauling_date' => 'date',
            'quantity' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function wasteRecord(): BelongsTo
    {
        return $this->belongsTo(WasteRecord::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeVisibleForRemaining($query)
    {
        return $query->whereIn('status', ['pending_approval', 'approved']);
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function canBeRejected(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval'], true);
    }

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

    public function reject(string $rejectedBy, string $reason): bool
    {
        if (! $this->canBeRejected()) {
            return false;
        }

        $this->status = 'rejected';
        $this->approved_by = $rejectedBy;
        $this->approved_at = now();
        $this->rejection_reason = $reason;

        return $this->save();
    }

    public function cancel(): bool
    {
        if (! $this->canBeCancelled()) {
            return false;
        }

        $this->status = 'cancelled';

        return $this->save();
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'pending_approval' => 'Menunggu Persetujuan',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'cancelled' => 'Dibatalkan',
            default => ucfirst($this->status),
        };
    }
}
