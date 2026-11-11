<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WasteHaulingPlan extends Model
{
    use \App\Concerns\RestoresOperationalWrites, \App\Concerns\SerializesOperationalWrites, HasUuids, SoftDeletes {
        SoftDeletes::restore as protected restoreWithSoftDeletes;
        \App\Concerns\RestoresOperationalWrites::restore insteadof SoftDeletes;
    }

    protected $fillable = ['waste_record_id', 'planned_date', 'quantity', 'unit', 'notes', 'status', 'created_by', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'approval_notes', 'rejection_reason', 'closed_by', 'closed_at', 'closure_reason'];

    protected function casts(): array
    {
        return ['planned_date' => 'date', 'quantity' => 'decimal:2', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function wasteRecord(): BelongsTo
    {
        return $this->belongsTo(WasteRecord::class);
    }

    public function haulings(): HasMany
    {
        return $this->hasMany(WasteHauling::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function canBeClosed(): bool
    {
        return $this->status === 'approved';
    }

    public function approve(string $actor, ?string $notes = null): bool
    {
        return $this->transitionOperationally('canBeApproved', ['status' => 'approved', 'approved_by' => $actor, 'approved_at' => now(), 'approval_notes' => $notes]);
    }

    public function reject(string $actor, string $reason): bool
    {
        return $this->transitionOperationally('canBeApproved', ['status' => 'rejected', 'approved_by' => $actor, 'approved_at' => now(), 'rejection_reason' => $reason]);
    }

    public function close(string $actor, string $reason): bool
    {
        return $this->transitionOperationally('canBeClosed', ['status' => 'closed', 'closed_by' => $actor, 'closed_at' => now(), 'closure_reason' => $reason]);
    }
}
