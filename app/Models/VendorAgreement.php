<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorAgreement extends Model
{
    use HasUuids;

    protected $fillable = ['transporter_vendor_id', 'receiver_vendor_id', 'document_number', 'valid_from', 'valid_until', 'waste_classification', 'document_status', 'notes', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_until' => 'date'];
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'transporter_vendor_id')->withTrashed();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'receiver_vendor_id')->withTrashed();
    }

    public function periodStatus(): string
    {
        if (! $this->valid_from || ! $this->valid_until) {
            return 'Belum lengkap';
        }
        $today = now()->startOfDay();
        if ($this->valid_from->gt($today)) {
            return 'Belum mulai';
        }
        if ($this->valid_until->lt($today)) {
            return 'Berakhir';
        }

        return 'Dalam rentang tanggal';
    }
}
