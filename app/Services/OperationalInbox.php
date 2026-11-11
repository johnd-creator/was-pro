<?php

namespace App\Services;

use App\Http\Middleware\UseOrganizationContext;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaMovement;
use App\Models\OperationalNotification;
use App\Models\Organization;
use App\Models\User;
use App\Models\WasteHauling;
use App\Models\WasteRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationalInbox
{
    public const CAPTURE = 'operational_inbox_capture';

    private const MODELS = [
        'waste_record' => WasteRecord::class,
        'waste_hauling' => WasteHauling::class,
        'waste_hauling_plan' => \App\Models\WasteHaulingPlan::class,
        'faba_movement' => FabaMovement::class,
        'faba_monthly' => FabaMonthlyApproval::class,
    ];

    public function organization(): ?Organization
    {
        $schema = DB::connection()->selectOne('SELECT current_schema() AS schema', [], false)?->schema;
        if (! $schema || $schema === 'public') {
            return null;
        }
        $organization = Organization::query()->where('schema_name', $schema)->where('is_active', true)->first();
        $active = request()->attributes->get(UseOrganizationContext::ATTRIBUTE);
        if ($active && (string) $active->id !== (string) $organization?->id) {
            return null;
        }

        return $organization;
    }

    public function available(): bool
    {
        if (! config('operational-inbox.enabled') || ! auth()->user()) {
            return false;
        }
        $organization = $this->organization();

        return $organization && auth()->user()->canAccessOrganization((string) $organization->id)
            && Schema::hasColumns($organization->schema_name.'.operational_notifications', [
                'id', 'event_id', 'organization_id', 'recipient_id', 'resource_type', 'resource_id', 'event', 'occurred_at', 'read_at',
            ]);
    }

    /** Capture only real workflow transitions inside an authenticated HTTP command transaction. */
    public function recordTransition(Model $model, array $before, string $eventId): void
    {
        if (! request()->attributes->get(self::CAPTURE) || DB::connection()->transactionLevel() < 1 || ! $this->available()) {
            return;
        }
        $type = array_search($model::class, self::MODELS, true);
        if ($type === false) {
            return;
        }
        $column = $model instanceof FabaMovement ? 'approval_status' : 'status';
        $previous = $before[$column] ?? null;
        $status = $model->getAttribute($column);
        $pending = $model instanceof WasteRecord ? 'pending_review' : ($model instanceof FabaMonthlyApproval ? 'submitted' : 'pending_approval');
        if ($previous === $status || ! in_array($status, [$pending, 'approved', 'rejected'], true)) {
            return;
        }
        if ($status !== $pending && $previous !== $pending) {
            return;
        }
        $organization = $this->organization();
        $event = $status === $pending ? 'submitted' : $status;
        $users = User::query()->with('role.permissions')->where('id', '!=', auth()->id());
        if ($event === 'submitted') {
            $users->where('organization_id', $organization->id);
        } else {
            $users->where('id', $model->submitted_by);
        }
        foreach ($users->get() as $recipient) {
            if (! $recipient->canAccessOrganization((string) $organization->id)
                || ! $this->readableResources($type, $recipient)->whereKey($model->getKey())->exists()
                || ($event === 'submitted' && (! $this->canReview($type, $recipient) || ! app(ApprovalSeparation::class)->canApprove($recipient, $model)))) {
                continue;
            }
            OperationalNotification::query()->firstOrCreate([
                'id' => hash('sha256', $organization->id.':'.$eventId.':'.$recipient->id),
            ], [
                'event_id' => $eventId,
                'organization_id' => $organization->id,
                'recipient_id' => $recipient->id,
                'resource_type' => $type,
                'resource_id' => $model->getKey(),
                'event' => $event,
                'occurred_at' => now(),
            ]);
        }
    }

    public function visibleQuery(User $user): Builder
    {
        $organization = $this->organization();
        $query = OperationalNotification::query()->where('recipient_id', $user->id)->where('organization_id', $organization?->id);
        if (! $organization || ! $user->canAccessOrganization((string) $organization->id)) {
            return $query->whereRaw('false');
        }

        return $query->where(function (Builder $types) use ($user): void {
            foreach (self::MODELS as $type => $class) {
                if ($type === 'waste_hauling_plan' && ! app(HaulingPlans::class)->enabled()) {
                    continue;
                }
                $types->orWhere(function (Builder $notifications) use ($type, $user): void {
                    $notifications->where('resource_type', $type)
                        ->whereIn('resource_id', $this->readableResources($type, $user)->select('id'))
                        ->where(function (Builder $events) use ($type, $user): void {
                            $events->where('event', '!=', 'submitted');
                            if ($this->canReview($type, $user)) {
                                $eligible = $this->readableResources($type, $user);
                                foreach ($type === 'faba_monthly' ? ['submitted_by'] : ['created_by', 'submitted_by'] as $column) {
                                    $eligible->where(fn (Builder $owners) => $owners->whereNull($column)->orWhere($column, '!=', $user->id));
                                }
                                $events->orWhereIn('resource_id', $eligible->select('id'));
                            }
                        });
                });
            }
        });
    }

    private function canReview(string $type, User $user): bool
    {
        $prefix = match ($type) {
            'waste_record' => 'waste_records',
            'waste_hauling', 'waste_hauling_plan' => 'waste_hauling',
            default => 'faba_approvals',
        };

        return $user->hasPermission($prefix.'.approve') || $user->hasPermission($prefix.'.reject');
    }

    private function readableResources(string $type, User $user): Builder
    {
        $query = self::MODELS[$type]::query();
        if ($type === 'faba_monthly') {
            return $user->hasPermission('faba_approvals.view') ? $query : $query->whereRaw('false');
        }
        if ($type === 'faba_movement') {
            $groups = [
                'faba_production.view' => [FabaMovement::TYPE_PRODUCTION, FabaMovement::TYPE_WORKSHOP, FabaMovement::TYPE_REJECT, FabaMovement::TYPE_DISPOSAL_POK],
                'faba_utilization.view' => [FabaMovement::TYPE_UTILIZATION_EXTERNAL, FabaMovement::TYPE_UTILIZATION_INTERNAL],
                'faba_adjustments.view' => [FabaMovement::TYPE_ADJUSTMENT_IN, FabaMovement::TYPE_ADJUSTMENT_OUT],
            ];
            $allowed = [];
            foreach ($groups as $permission => $values) {
                if ($user->hasPermission($permission)) {
                    $allowed = [...$allowed, ...$values];
                }
            }

            return $query->whereIn('movement_type', $allowed);
        }
        $prefix = $type === 'waste_record' ? 'waste_records' : 'waste_hauling';
        if ($user->hasPermission($prefix.'.view_all') || (in_array($type, ['waste_hauling', 'waste_hauling_plan'], true) && $this->canReview($type, $user))) {
            return $query;
        }

        return $user->hasPermission($prefix.'.view_own') ? $query->where('created_by', $user->id) : $query->whereRaw('false');
    }

    private function sourceDetails(Model $resource, string $organizationId): array
    {
        if ($resource instanceof WasteRecord) {
            return [
                'reference' => $resource->record_number,
                'source_date' => $resource->date?->toDateString(),
                'source_url' => route('waste-management.records.show', [$resource->id, 'organization_id' => $organizationId]),
            ];
        }
        if ($resource instanceof \App\Models\WasteHaulingPlan) {
            return ['reference' => 'Rencana '.$resource->wasteRecord?->record_number, 'source_date' => $resource->planned_date?->toDateString(), 'source_url' => route('waste-management.hauling-plans.show', [$resource->id, 'organization_id' => $organizationId])];
        }
        if ($resource instanceof WasteHauling) {
            return [
                'reference' => $resource->hauling_number,
                'source_date' => $resource->hauling_date?->toDateString(),
                'source_url' => route('waste-management.haulings.show', [$resource->id, 'organization_id' => $organizationId]),
            ];
        }
        if ($resource instanceof FabaMonthlyApproval) {
            return [
                'reference' => sprintf('Closing FABA %04d-%02d', $resource->year, $resource->month),
                'source_date' => sprintf('%04d-%02d', $resource->year, $resource->month),
                'source_url' => route('waste-management.faba.approvals.review', [$resource->year, $resource->month, 'organization_id' => $organizationId]),
            ];
        }
        $route = match ($resource->movement_type) {
            FabaMovement::TYPE_UTILIZATION_EXTERNAL, FabaMovement::TYPE_UTILIZATION_INTERNAL => 'waste-management.faba.utilization.show',
            FabaMovement::TYPE_ADJUSTMENT_IN, FabaMovement::TYPE_ADJUSTMENT_OUT => 'waste-management.faba.adjustments.show',
            default => 'waste-management.faba.production.show',
        };

        return [
            'reference' => $resource->document_number ?: 'FABA '.$resource->id,
            'source_date' => $resource->transaction_date?->toDateString(),
            'source_url' => route($route, [$resource->id, 'organization_id' => $organizationId]),
        ];
    }

    public function page(User $user, int $page): array
    {
        $query = $this->visibleQuery($user);
        $unread = (clone $query)->whereNull('read_at')->count();
        $rows = $query->orderByDesc('occurred_at')->orderByDesc('id')->offset(($page - 1) * 20)->limit(21)->get();
        $resources = [];
        foreach ($rows->groupBy('resource_type') as $type => $group) {
            $resources[$type] = $this->readableResources($type, $user)->whereIn('id', $group->pluck('resource_id'))->get()->keyBy('id');
        }
        $organizationId = (string) $this->organization()->id;
        $labels = ['waste_record' => 'Catatan limbah', 'waste_hauling' => 'Realisasi pengangkutan limbah', 'waste_hauling_plan' => 'Rencana pengangkutan limbah', 'faba_movement' => 'Transaksi FABA', 'faba_monthly' => 'Closing bulanan FABA'];
        $events = ['submitted' => 'diajukan untuk persetujuan', 'approved' => 'disetujui', 'rejected' => 'ditolak'];

        return [
            'unreadCount' => $unread,
            'page' => $page,
            'hasMore' => $rows->count() > 20,
            'notifications' => $rows->take(20)->map(function (OperationalNotification $row) use ($resources, $labels, $events, $organizationId): ?array {
                $resource = $resources[$row->resource_type]->get($row->resource_id);
                if (! $resource) {
                    return null;
                }

                return [
                    ...$this->sourceDetails($resource, $organizationId),
                    'id' => $row->id,
                    'event' => $row->event,
                    'label' => $labels[$row->resource_type].' '.$events[$row->event].'.',
                    'occurred_at' => $row->occurred_at->toIso8601String(),
                    'read_at' => $row->read_at?->toIso8601String(),
                    'current_status' => $resource instanceof FabaMovement ? $resource->approval_status : $resource->status,
                ];
            })->filter()->values()->all(),
        ];
    }
}
