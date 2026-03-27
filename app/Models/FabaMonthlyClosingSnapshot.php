<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FabaMonthlyClosingSnapshot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'year',
        'month',
        'status',
        'approved_by',
        'approved_at',
        'snapshot_payload',
        'warning_summary',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'approved_at' => 'datetime',
            'snapshot_payload' => 'array',
            'warning_summary' => 'array',
        ];
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }
}
