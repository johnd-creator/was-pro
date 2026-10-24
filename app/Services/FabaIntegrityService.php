<?php

namespace App\Services;

use App\Exceptions\OperationalConflict;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaMovement;
use Carbon\CarbonImmutable;

class FabaIntegrityService
{
    public function capture(): array
    {
        $days = FabaMovement::query()->approved()->orderBy('transaction_date')->orderBy('created_at')->orderBy('id')->get()
            ->groupBy(fn (FabaMovement $movement): string => $movement->material_type.':'.$movement->transaction_date->toDateString());
        $balances = [];
        $timeline = [];
        foreach ($days as $key => $movements) {
            $material = $movements->first()->material_type;
            $opening = $movements->where('movement_type', FabaMovement::TYPE_OPENING_BALANCE)->last();
            $balances[$material] = $opening ? (float) $opening->quantity : ($balances[$material] ?? 0.0);
            foreach ($movements as $movement) {
                if ($movement->movement_type !== FabaMovement::TYPE_OPENING_BALANCE) {
                    $balances[$material] += ($movement->stock_effect === 'in' ? 1 : -1) * (float) $movement->quantity;
                }
            }
            $timeline[$key] = round($balances[$material], 2);
        }

        $locked = [];
        $ledger = app(FabaMovementLedgerService::class);
        foreach (FabaMonthlyApproval::query()->whereIn('status', ['submitted', 'approved'])->get() as $period) {
            $date = CarbonImmutable::create($period->year, $period->month, 1)->endOfMonth();
            foreach (FabaMovement::materialOptions() as $material) {
                $locked[$period->year.':'.$period->month.':'.$material] = $ledger->calculateAvailableStock($material, $date);
            }
        }

        return ['timeline' => $timeline, 'locked' => $locked];
    }

    public function validate(array $before): void
    {
        $after = $this->capture();
        $baseline = [];
        foreach ($before['timeline'] as $key => $balance) {
            [$material, $date] = explode(':', $key);
            $baseline[$material][] = [$date, $balance];
        }
        $indices = [];
        $previousBalances = [];
        foreach ($after['timeline'] as $key => $balance) {
            [$material, $date] = explode(':', $key);
            $indices[$material] ??= 0;
            while (isset($baseline[$material][$indices[$material]]) && $baseline[$material][$indices[$material]][0] <= $date) {
                $previousBalances[$material] = $baseline[$material][$indices[$material]][1];
                $indices[$material]++;
            }
            if ($balance < min(0, $previousBalances[$material] ?? 0)) {
                throw new OperationalConflict('Perubahan menyebabkan saldo FABA negatif pada transaksi berikutnya.');
            }
        }
        $ledger = app(FabaMovementLedgerService::class);
        foreach ($before['locked'] as $key => $balance) {
            [$year, $month, $material] = explode(':', $key);
            $date = CarbonImmutable::create((int) $year, (int) $month, 1)->endOfMonth();
            if ($ledger->calculateAvailableStock($material, $date) !== $balance) {
                throw new OperationalConflict('Perubahan memengaruhi saldo periode terkunci. Lakukan reopen terlebih dahulu.');
            }
        }
    }
}
