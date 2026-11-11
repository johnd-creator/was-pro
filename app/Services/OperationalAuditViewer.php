<?php

namespace App\Services;

use App\Models\OperationalAuditLog;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class OperationalAuditViewer
{
    /** Explicit display allowlist also redacts unsafe fields retained in older snapshots. */
    public const FIELDS = ['waste_hauling_plan_id', 'planned_date', 'closed_by', 'closed_at', 'closure_reason', 'quantity', 'unit', 'date', 'hauling_date', 'waste_record_id', 'waste_type_id', 'record_number', 'hauling_number', 'status', 'created_by', 'updated_by', 'submitted_by', 'approved_by', 'deleted_at', 'vendor_id', 'vehicle_number', 'driver_name', 'document_number', 'destination', 'receiver', 'source', 'description', 'notes', 'expiry_date', 'rejection_reason', 'approval_notes', 'submitted_at', 'approved_at'];

    public function query(User $user): Builder
    {
        $organization = app(OperationalInbox::class)->organization();
        abort_unless($organization && $user->canAccessOrganization((string) $organization->id) && $user->hasPermission('operational_audit.view'), 403);
        abort_unless(Schema::hasTable($organization->schema_name.'.operational_audit_logs'), 503, 'Riwayat audit belum tersedia untuk organisasi ini.');

        return OperationalAuditLog::query()->where(function (Builder $query) use ($user): void {
            $query->whereRaw('1 = 0');
            foreach (['waste_records' => [WasteRecord::class, 'waste_records'], 'waste_haulings' => [WasteHauling::class, 'waste_hauling'], 'waste_hauling_plans' => [\App\Models\WasteHaulingPlan::class, 'waste_hauling']] as $module => [$model, $permission]) {
                if ($module === 'waste_hauling_plans' && ! app(HaulingPlans::class)->installed()) {
                    continue;
                }
                $all = $user->hasPermission($permission.'.view_all');
                if (! $all && ! $user->hasPermission($permission.'.view_own')) {
                    continue;
                }
                $visible = $model::query()->select('id')->when(! $all, fn ($rows) => $rows->where('created_by', $user->id));
                $query->orWhere(fn ($rows) => $rows->where('module', $module)->whereIn('reference_id', $visible));
            }
        });
    }

    public function serialize(OperationalAuditLog $log): array
    {
        $details = $log->details ?? [];
        $fields = array_flip(self::FIELDS);
        $clean = fn ($snapshot) => array_filter(array_intersect_key(is_array($snapshot) ? $snapshot : [], $fields), fn ($value) => is_scalar($value) || $value === null);

        return ['id' => $log->id, 'actor_id' => $log->actor_id, 'module' => $log->module, 'action' => $log->action, 'reference_id' => $log->reference_id, 'occurred_at' => $log->created_at?->toIso8601String(), 'before' => $clean($details['before'] ?? []), 'after' => $clean($details['after'] ?? [])];
    }
}
