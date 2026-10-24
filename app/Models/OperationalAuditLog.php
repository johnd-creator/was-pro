<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OperationalAuditLog extends Model
{
    use HasUuids;

    protected $fillable = ['actor_id', 'module', 'action', 'reference_id', 'details'];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
