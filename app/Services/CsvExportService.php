<?php

namespace App\Services;

class CsvExportService
{
    /**
     * @param  resource  $stream
     * @param  array<array-key, mixed>  $values
     */
    public static function writeRow($stream, array $values): void
    {
        fputcsv($stream, array_map(
            fn (mixed $value): mixed => is_string($value) && preg_match('/^(?:\s*[=+@\-]|[\t\r\n])/u', $value) ? "'".$value : $value,
            $values,
        ), ',', '"', '');
    }
}
