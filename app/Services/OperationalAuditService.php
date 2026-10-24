<?php

namespace App\Services;

use App\Models\OperationalAuditLog;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use Illuminate\Database\Eloquent\Model;

class OperationalAuditService
{
    public function record(Model $model, array $before, string $action): void
    {
        if (! $model instanceof WasteRecord && ! $model instanceof WasteHauling) {
            return;
        }
        $fields = array_flip(['quantity', 'unit', 'date', 'hauling_date', 'waste_record_id', 'waste_type_id', 'record_number', 'hauling_number', 'status', 'created_by', 'updated_by', 'submitted_by', 'approved_by', 'deleted_at']);
        $actor = auth()->id();
        if (! $actor && ($before['status'] ?? null) !== $model->status) {
            $actor = match ($model->status) {
                'approved', 'rejected' => $model->approved_by,
                'pending_review' => $model->submitted_by,
                default => null,
            };
        }
        OperationalAuditLog::query()->create([
            'actor_id' => $actor,
            'module' => $model->getTable(), 'action' => $action, 'reference_id' => $model->getKey(),
            'details' => ['before' => array_intersect_key($before, $fields), 'after' => array_intersect_key($model->getAttributes(), $fields)],
        ]);
    }
}
