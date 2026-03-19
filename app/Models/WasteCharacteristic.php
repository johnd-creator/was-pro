<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WasteCharacteristic extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_hazardous',
        'is_active',
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
            'is_hazardous' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the waste types for the characteristic.
     */
    public function wasteTypes(): HasMany
    {
        return $this->hasMany(WasteType::class, 'characteristic_id');
    }

    /**
     * Scope to filter only active characteristics.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter hazardous characteristics.
     */
    public function scopeHazardous($query)
    {
        return $query->where('is_hazardous', true);
    }

    /**
     * Scope to filter non-hazardous characteristics.
     */
    public function scopeNonHazardous($query)
    {
        return $query->where('is_hazardous', false);
    }
}
