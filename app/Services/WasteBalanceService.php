<?php

namespace App\Services;

use App\Models\User;
use App\Models\WasteRecord;

class WasteBalanceService
{
    public const BASIS = 'Berdasarkan tanggal transaksi dan status approved saat laporan dibuka. Saldo awal = masuk sebelum periode dikurangi keluar sebelum periode. Saldo akhir = saldo awal + masuk - keluar. Bukan snapshot status persetujuan historis atau hasil pengukuran stok fisik. Data mengikuti hak akses catatan Anda; satuan tidak dikonversi.';

    public function report(User $user, string $start, string $end): array
    {
        $records = WasteRecord::query()->approved()
            ->when(! $user->hasPermission('waste_records.view_all'), fn ($query) => $query->byUser($user->id))
            ->where(fn ($query) => $query->whereDate('date', '<=', $end)
                ->orWhereHas('haulings', fn ($haulings) => $haulings->approved()->whereDate('hauling_date', '<=', $end)))
            ->with(['wasteType', 'haulings' => fn ($query) => $query->approved()->whereDate('hauling_date', '<=', $end)->orderBy('hauling_date')->orderBy('id')])
            ->orderBy('date')->orderBy('id')->get();
        $rows = [];
        foreach ($records as $record) {
            $references = [];
            $date = $record->date->toDateString();
            if ($date <= $end) {
                $this->add($references, $record->unit, $date < $start ? 'opening' : 'incoming', $this->hundredths($record->quantity));
            }
            foreach ($record->haulings as $hauling) {
                $haulingDate = $hauling->hauling_date->toDateString();
                $quantity = $this->hundredths($hauling->quantity);
                $this->add($references, $hauling->unit, $haulingDate < $start ? 'opening' : 'outgoing', $haulingDate < $start ? -$quantity : $quantity);
                if ($haulingDate < $date) {
                    $references[$hauling->unit]['anomalies'][] = $hauling->hauling_number.': tanggal pengangkutan sebelum tanggal catatan.';
                }
                if ($hauling->unit !== $record->unit) {
                    $references[$hauling->unit]['anomalies'][] = $hauling->hauling_number.': satuan pengangkutan berbeda dari catatan ('.$record->unit.').';
                }
            }
            foreach ($references as $unit => $reference) {
                $reference['closing'] = $reference['opening'] + $reference['incoming'] - $reference['outgoing'];
                if ($reference['opening'] < 0 || $reference['closing'] < 0) {
                    $reference['anomalies'][] = 'Saldo awal atau akhir catatan negatif.';
                }
                $key = $record->waste_type_id.'|'.$unit;
                $rows[$key] ??= [
                    'waste_type_id' => $record->waste_type_id,
                    'waste_type' => $record->wasteType?->name ?? 'Jenis tidak tersedia',
                    'code' => $record->wasteType?->code ?? '',
                    'unit' => (string) $unit,
                    ...$this->emptyAmounts(),
                    'references' => [],
                ];
                foreach (['opening', 'incoming', 'outgoing', 'closing'] as $field) {
                    $rows[$key][$field] += $reference[$field];
                }
                $rows[$key]['references'][] = [
                    'id' => $record->id,
                    'record_number' => $record->record_number,
                    'date' => $date,
                    'source' => $record->source,
                    ...$this->decimalAmounts($reference),
                    'anomalies' => $reference['anomalies'],
                ];
            }
        }
        $totals = [];
        foreach ($rows as &$row) {
            $unit = $row['unit'];
            $totals[$unit] ??= ['unit' => $unit, ...$this->emptyAmounts()];
            foreach (['opening', 'incoming', 'outgoing', 'closing'] as $field) {
                $totals[$unit][$field] += $row[$field];
            }
            $row['anomaly_count'] = array_sum(array_map(fn (array $reference): int => count($reference['anomalies']), $row['references']));
            $row = [...$row, ...$this->decimalAmounts($row)];
        }
        unset($row);

        return [
            'basis' => self::BASIS,
            'start_date' => $start,
            'end_date' => $end,
            'rows' => collect($rows)->sortBy(fn (array $row): string => $row['waste_type'].'|'.$row['unit'])->values()->all(),
            'totals' => collect($totals)->sortKeys()->map(fn (array $total): array => [...$total, ...$this->decimalAmounts($total)])->values()->all(),
        ];
    }

    private function hundredths(string $quantity): int
    {
        return (int) str_replace('.', '', $quantity);
    }

    private function emptyAmounts(): array
    {
        return ['opening' => 0, 'incoming' => 0, 'outgoing' => 0, 'closing' => 0];
    }

    private function add(array &$references, string $unit, string $field, int $quantity): void
    {
        $references[$unit] ??= [...$this->emptyAmounts(), 'anomalies' => []];
        $references[$unit][$field] += $quantity;
    }

    private function decimalAmounts(array $amounts): array
    {
        return collect($amounts)->only(['opening', 'incoming', 'outgoing', 'closing'])
            ->map(fn (int $amount): float => $amount / 100)->all();
    }
}
