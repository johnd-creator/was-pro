<?php

namespace App\Services;

use App\Exceptions\OperationalConflict;
use App\Models\FabaAuditLog;
use App\Models\FabaMovement;
use Illuminate\Validation\ValidationException;

class FabaCorrectionSource
{
    public function attributes(array $input, ?FabaMovement $current = null): array
    {
        $reserved = $current?->reference_type && $current->reference_type !== FabaMovement::class;
        if ($reserved) {
            if (! empty($input['source_movement_id'])) {
                throw ValidationException::withMessages(['source_movement_id' => 'Referensi migrasi atau saldo awal tidak dapat diganti.']);
            }

            return [];
        }
        $provided = array_key_exists('source_movement_id', $input);
        $id = $provided ? $input['source_movement_id'] : $current?->reference_id;
        if ($id) {
            $source = FabaMovement::query()->approved()->find($id);
            if (! $source || ! $this->canRead($source->movement_type) || $id === $current?->id || $source->material_type !== $input['material_type']) {
                throw ValidationException::withMessages(['source_movement_id' => 'Pilih transaksi asal yang disetujui, pada organisasi dan material yang sama, selain adjustment ini.']);
            }
        }

        return $provided ? ['reference_type' => $id ? FabaMovement::class : null, 'reference_id' => $id] : [];
    }

    public function capture(FabaMovement $movement, ?string $previousSourceId = null): void
    {
        if ($movement->reference_type !== FabaMovement::class || ! $movement->reference_id) {
            return;
        }
        $previous = $this->evidence($movement);
        if ($previousSourceId === $movement->reference_id && ($previous['source_snapshot']['id'] ?? null) === $movement->reference_id) {
            return;
        }
        $source = FabaMovement::query()->findOrFail($movement->reference_id);
        app(FabaAuditService::class)->log(auth()->id(), 'source_linked', FabaAuditLog::MODULE_ADJUSTMENT,
            FabaMovement::class, $movement->id, $movement->period_year, $movement->period_month,
            'Bukti transaksi asal saat koreksi ditautkan.', [
                'captured_at' => now()->toIso8601String(),
                'source_snapshot' => $this->describe($source),
            ]);
    }

    public function serialize(FabaMovement $movement): array
    {
        $linked = $movement->reference_type === FabaMovement::class && $movement->reference_id;
        $source = $linked ? FabaMovement::query()->find($movement->reference_id) : null;
        $evidence = $linked ? $this->evidence($movement) : [];
        if ($source && ! $this->canRead($source->movement_type)) {
            $source = null;
        }
        if (isset($evidence['source_snapshot']['movement_type']) && ! $this->canRead($evidence['source_snapshot']['movement_type'])) {
            $evidence = [];
        }

        return [
            'source_movement_id' => $linked ? $movement->reference_id : null,
            'source_reference_reserved' => (bool) ($movement->reference_type && $movement->reference_type !== FabaMovement::class),
            'source_current' => $source ? $this->describe($source) : null,
            'source_snapshot' => $evidence['source_snapshot'] ?? null,
            'source_captured_at' => $evidence['captured_at'] ?? null,
        ];
    }

    public function options(): array
    {
        $types = array_values(array_filter(FabaMovement::movementTypes(), fn (string $type): bool => $this->canRead($type)));

        return FabaMovement::query()->approved()->whereIn('movement_type', $types)->orderByDesc('transaction_date')->get()
            ->map(fn (FabaMovement $source): array => $this->describe($source))->all();
    }

    protected function canRead(string $type): bool
    {
        $permission = match (true) {
            in_array($type, FabaMovement::productionTypeOptions(), true) => 'faba_production.view',
            in_array($type, FabaMovement::utilizationTypeOptions(), true) => 'faba_utilization.view',
            in_array($type, [FabaMovement::TYPE_ADJUSTMENT_IN, FabaMovement::TYPE_ADJUSTMENT_OUT], true) => 'faba_adjustments.view',
            $type === FabaMovement::TYPE_OPENING_BALANCE => 'faba_recaps.view',
            default => null,
        };

        return $permission && (auth()->user()?->hasPermission($permission) ?? false);
    }

    public function assertDeletable(FabaMovement $source): void
    {
        if (FabaMovement::query()->where('reference_type', FabaMovement::class)->where('reference_id', $source->id)->exists()) {
            throw new OperationalConflict('Transaksi asal masih dirujuk oleh adjustment dan tidak dapat dihapus.');
        }
    }

    protected function evidence(FabaMovement $movement): array
    {
        return FabaAuditLog::query()->where('reference_type', FabaMovement::class)
            ->where('reference_id', $movement->id)->where('action', 'source_linked')
            ->latest('created_at')->latest('id')->first()?->details ?? [];
    }

    protected function describe(FabaMovement $source): array
    {
        return [
            'id' => $source->id,
            'transaction_date' => $source->transaction_date->toDateString(),
            'material_type' => $source->material_type,
            'movement_type' => $source->movement_type,
            'quantity' => (float) $source->quantity,
            'unit' => $source->unit,
            'document_number' => $source->document_number,
        ];
    }
}
