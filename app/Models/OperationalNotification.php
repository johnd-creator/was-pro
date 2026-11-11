<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalNotification extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'event_id', 'organization_id', 'recipient_id', 'resource_type', 'resource_id', 'event', 'occurred_at', 'read_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'read_at' => 'datetime'];
    }
}
