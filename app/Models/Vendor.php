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
        'is_transporter',
        'is_processor',
        'is_utilizer',
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
            'is_transporter' => 'boolean',
            'is_processor' => 'boolean',
            'is_utilizer' => 'boolean',
        ];
    }

    /**
     * Get the FABA movements that reference the vendor.
     */
    public function fabaMovements(): HasMany
    {
        return $this->hasMany(FabaMovement::class);
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
        return $this->licenseExpiryStatus() === 'not_expired';
    }

    /** Recorded expiry only, not legal verification. Calendar expiry day is inclusive. */
    public function licenseExpiryStatus(?string $referenceDate = null): string
    {
        if (! $this->license_expiry_date) {
            return 'unknown';
        }
        $date = $referenceDate ?? now()->toDateString();

        return $this->license_expiry_date->format('Y-m-d') < $date ? 'expired' : 'not_expired';
    }
}
