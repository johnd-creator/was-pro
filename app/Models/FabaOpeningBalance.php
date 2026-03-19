<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FabaOpeningBalance extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'year',
        'month',
        'material_type',
        'quantity',
        'note',
        'set_by',
        'set_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'quantity' => 'decimal:2',
            'set_at' => 'datetime',
        ];
    }

    public function setByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function scopeForPeriod($query, int $year, int $month)
    {
        return $query->where('year', $year)->where('month', $month);
    }
}
