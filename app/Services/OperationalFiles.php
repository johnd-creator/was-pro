<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class OperationalFiles
{
    private array $created = [];

    public function track(string|false $path, string $disk): void
    {
        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Lampiran gagal disimpan.');
        }
        $this->created[] = [$disk, $path];
    }

    public function rollback(): void
    {
        foreach ($this->created as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }
        $this->created = [];
    }

    public function commit(): void
    {
        $this->created = [];
    }
}
