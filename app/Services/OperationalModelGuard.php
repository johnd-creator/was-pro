<?php

namespace App\Services;

use App\Exceptions\OperationalConflict;
use App\Models\FabaMovement;
use App\Models\FabaOpeningBalance;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use Illuminate\Database\Eloquent\Model;

class OperationalModelGuard
{
    public function before(Model $model, ?Model $current): ?array
    {
        $restoringHauling = $model instanceof WasteHauling && $current && $current->deleted_at !== null && $model->deleted_at === null;
        if ($model instanceof WasteHauling && $current && (($model->isDirty('status') && $model->status === 'approved') || ($restoringHauling && in_array($model->status, ['pending_approval', 'approved'], true)))) {
            $record = WasteRecord::query()->findOrFail($model->waste_record_id);
            if (! $record->isApproved() || round((float) $model->quantity, 2) > round((float) $record->quantity - $record->getReservedHaulingQuantity($model->id), 2)) {
                throw new OperationalConflict('Jumlah pengangkutan melebihi sisa limbah yang tersedia.');
            }
        }
        $existingFabaMutation = $model instanceof FabaMovement && $current;
        if ($model instanceof FabaOpeningBalance || $existingFabaMutation) {
            $year = $model instanceof FabaOpeningBalance ? $model->year : $model->period_year;
            $month = $model instanceof FabaOpeningBalance ? $model->month : $model->period_month;
            if (app(FabaRecapService::class)->isPeriodLocked($year, $month)) {
                throw new OperationalConflict('Periode transaksi sedang terkunci.');
            }
            if ($current instanceof FabaMovement && app(FabaRecapService::class)->isPeriodLocked($current->period_year, $current->period_month)) {
                throw new OperationalConflict('Periode transaksi asal sedang terkunci.');
            }

            return app(FabaIntegrityService::class)->capture();
        }

        return null;
    }

    public function after(?array $before): void
    {
        if ($before !== null) {
            app(FabaIntegrityService::class)->validate($before);
        }
    }
}
