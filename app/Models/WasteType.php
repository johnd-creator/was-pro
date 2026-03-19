<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WasteType extends Model
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
        'category_id',
        'characteristic_id',
        'description',
        'storage_period_days',
        'transport_cost',
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
            'storage_period_days' => 'integer',
            'transport_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the category for the waste type.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'category_id');
    }

    /**
     * Get the characteristic for the waste type.
     */
    public function characteristic(): BelongsTo
    {
        return $this->belongsTo(WasteCharacteristic::class, 'characteristic_id');
    }

    /**
     * Get the waste records for the waste type.
     */
    public function wasteRecords(): HasMany
    {
        return $this->hasMany(WasteRecord::class);
    }

    /**
     * Scope to filter only active waste types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category.
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope to filter by characteristic.
     */
    public function scopeByCharacteristic($query, $characteristicId)
    {
        return $query->where('characteristic_id', $characteristicId);
    }

    /**
     * Scope to filter hazardous waste types.
     */
    public function scopeHazardous($query)
    {
        return $query->whereHas('characteristic', function ($q) {
            $q->where('is_hazardous', true);
        });
    }
}
