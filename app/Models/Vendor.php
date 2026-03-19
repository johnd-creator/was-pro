<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
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
        'contact_person',
        'phone',
        'email',
        'address',
        'license_number',
        'license_expiry_date',
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
            'license_expiry_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the transportations for the vendor.
     */
    public function transportations(): HasMany
    {
        return $this->hasMany(WasteTransportation::class);
    }

    /**
     * Scope to filter only active vendors.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by license validity.
     */
    public function scopeWithValidLicense($query)
    {
        return $query->whereDate('license_expiry_date', '>=', now());
    }

    /**
     * Scope to filter by expired license.
     */
    public function scopeWithExpiredLicense($query)
    {
        return $query->whereDate('license_expiry_date', '<', now());
    }

    /**
     * Check if the vendor has a valid license.
     */
    public function hasValidLicense(): bool
    {
        return ! $this->license_expiry_date || $this->license_expiry_date->isFuture();
    }
}
