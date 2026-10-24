<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WasteHauling extends Model
{
    use \App\Concerns\RestoresOperationalWrites, \App\Concerns\SerializesOperationalWrites, HasFactory, HasUuids, SoftDeletes {
        SoftDeletes::restore as protected restoreWithSoftDeletes;
        \App\Concerns\RestoresOperationalWrites::restore insteadof SoftDeletes;
    }

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
                $hauling->hauling_number = app(\App\Services\DocumentNumberService::class)->next(self::class, 'hauling_number', $prefix);
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
        return $this->transitionOperationally('canBeApproved', ['status' => 'approved', 'approved_by' => $approvedBy, 'approved_at' => now(), 'approval_notes' => $notes]);
    }

    public function reject(string $rejectedBy, string $reason): bool
    {
        return $this->transitionOperationally('canBeRejected', ['status' => 'rejected', 'approved_by' => $rejectedBy, 'approved_at' => now(), 'rejection_reason' => $reason]);
    }

    public function cancel(): bool
    {
        return $this->transitionOperationally('canBeCancelled', ['status' => 'cancelled']);
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
