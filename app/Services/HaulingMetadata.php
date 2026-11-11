<?php

namespace App\Services;

use App\Http\Requests\WasteManagement\WasteHaulingRequest;
use App\Models\WasteHauling;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HaulingMetadata
{
    public const FIELDS = ['vendor_id', 'vehicle_number', 'driver_name', 'document_number', 'destination', 'receiver'];

    public function available(): bool
    {
        return Schema::hasColumns('waste_haulings', [...self::FIELDS, 'attachment_path']);
    }

    public function attributes(WasteHaulingRequest $request): array
    {
        if (! $this->available()) {
            return [];
        }
        $attributes = $request->safe()->only(self::FIELDS);
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('waste/hauling-attachments/'.app(TenantService::class)->getCurrentSchema(), 'local');
            app(OperationalFiles::class)->track($path, 'local');
            $attributes['attachment_path'] = $path;
        }

        return $attributes;
    }

    public function serialize(WasteHauling $hauling): array
    {
        $key = self::class.'.'.app(TenantService::class)->getCurrentSchema();
        $attributes = request()->attributes;
        if (! $attributes->has($key)) {
            $attributes->set($key, $this->available());
        }
        $available = $attributes->get($key);
        $metadata = ['metadata_available' => $available];
        foreach (self::FIELDS as $field) {
            $metadata[$field] = $available ? $hauling->getAttribute($field) : null;
        }
        $metadata['vendor_name'] = $available && $hauling->vendor_id
            ? $hauling->loadMissing('vendor')->vendor?->name : null;
        $metadata['has_attachment'] = $available && (bool) $hauling->getAttribute('attachment_path');

        return $metadata;
    }

    public function download(WasteHauling $hauling): StreamedResponse
    {
        abort_unless($this->available(), 404);
        $path = $hauling->attachment_path;
        $prefix = 'waste/hauling-attachments/'.app(TenantService::class)->getCurrentSchema().'/';
        abort_unless(is_string($path) && str_starts_with($path, $prefix) && ! str_contains($path, '..') && Storage::disk('local')->exists($path), 404);
        $response = Storage::disk('local')->download($path);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
