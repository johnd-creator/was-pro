<?php

namespace App\Services;

use App\Models\FabaMovement;
use App\Models\FabaOpeningBalance;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class FabaMovementLedgerService
{
    public function syncOpeningBalance(FabaOpeningBalance $balance): FabaMovement
    {
        return FabaMovement::query()->updateOrCreate(
            [
                'reference_type' => FabaOpeningBalance::class,
                'reference_id' => $balance->id,
            ],
            [
                'transaction_date' => sprintf('%04d-%02d-01', $balance->year, $balance->month),
                'material_type' => $balance->material_type,
                'movement_type' => FabaMovement::TYPE_OPENING_BALANCE,
                'stock_effect' => FabaMovement::STOCK_EFFECT_IN,
                'quantity' => $balance->quantity,
                'unit' => FabaMovement::DEFAULT_UNIT,
                'vendor_id' => null,
                'internal_destination_id' => null,
                'purpose_id' => null,
                'document_number' => null,
                'document_date' => null,
                'attachment_path' => null,
                'period_year' => $balance->year,
                'period_month' => $balance->month,
                'created_by' => $balance->set_by,
                'updated_by' => $balance->set_by,
                'note' => $balance->note,
            ],
        );
    }

    public function deleteOpeningBalanceMovement(FabaOpeningBalance $balance): void
    {
        $this->deleteReferenceMovements(FabaOpeningBalance::class, $balance->id);
    }

    public function deleteReferenceMovements(string $referenceType, string $referenceId): void
    {
        FabaMovement::query()
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->delete();
    }

    public function calculateAvailableStock(string $materialType, CarbonInterface $transactionDate, ?string $excludeMovementId = null): float
    {
        $query = FabaMovement::query()
            ->where('material_type', $materialType)
            ->whereDate('transaction_date', '<=', $transactionDate->toDateString());

        if ($excludeMovementId) {
            $query->whereKeyNot($excludeMovementId);
        }

        $inflow = (clone $query)
            ->where('stock_effect', FabaMovement::STOCK_EFFECT_IN)
            ->sum('quantity');

        $outflow = (clone $query)
            ->where('stock_effect', FabaMovement::STOCK_EFFECT_OUT)
            ->sum('quantity');

        return round((float) $inflow - (float) $outflow, 2);
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\FabaMovement>
     */
    public function findPotentialDuplicates(array $attributes, ?string $excludeMovementId = null): Collection
    {
        $query = FabaMovement::query()
            ->where('material_type', $attributes['material_type'])
            ->where('movement_type', $attributes['movement_type'])
            ->whereDate('transaction_date', $attributes['transaction_date'])
            ->where('quantity', $attributes['quantity']);

        if (array_key_exists('vendor_id', $attributes)) {
            $query->where('vendor_id', $attributes['vendor_id']);
        }

        if (array_key_exists('internal_destination_id', $attributes)) {
            $query->where('internal_destination_id', $attributes['internal_destination_id']);
        }

        if (array_key_exists('document_number', $attributes)) {
            $query->where('document_number', $attributes['document_number']);
        }

        if (array_key_exists('document_date', $attributes)) {
            $query->where('document_date', $attributes['document_date']);
        }

        if ($excludeMovementId) {
            $query->whereKeyNot($excludeMovementId);
        }

        return $query->get();
    }
}
