<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class ResourceVersion
{
    public static function of(Model $model): string
    {
        $attributes = $model->getAttributes();
        ksort($attributes);
        $normalized = array_map(fn ($value) => $value === null ? null : (string) $value, $attributes);

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }
}
