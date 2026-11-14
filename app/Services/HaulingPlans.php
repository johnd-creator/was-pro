<?php

namespace App\Services;

use App\Exceptions\OperationalConflict;
use App\Models\WasteHauling;
use App\Models\WasteHaulingPlan;
use App\Models\WasteRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class HaulingPlans
{
    public function installed(): bool
    {
        return Schema::hasTable('waste_hauling_plans') && Schema::hasColumn('waste_haulings', 'waste_hauling_plan_id');
    }

    public function enabled(): bool
    {
        return config('hauling-plans.enabled', false) && $this->installed();
    }

    /** Approved coordinator plans are shared only with operational users in the active tenant. */
    public function operatorPlans(): \Illuminate\Database\Eloquent\Builder
    {
        $query = WasteHaulingPlan::query()->where('status', 'approved');
        $user = auth()->user();
        $organization = app(OperationalInbox::class)->organization();
        if (! $this->enabled() || ! $user || $user->isSuperAdmin() || ($user->role?->slug !== 'operator' || ! $user->role?->is_active)
            || ! $organization || (string) $user->organization_id !== (string) $organization->id
            || ! $user->hasPermission('waste_hauling.view_own') || ! $user->hasPermission('waste_hauling.create')
            || ! $user->hasPermission('waste_hauling.submit')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('createdBy', fn ($creator) => $creator
            ->where('organization_id', $organization->id)
            ->whereHas('role', fn ($role) => $role->where('slug', 'coordinator')->where('is_active', true)));
    }

    public function canOperatePlan(mixed $planId, ?string $recordId = null): bool
    {
        return $this->enabled() && is_string($planId) && \Illuminate\Support\Str::isUuid($planId) && $this->operatorPlans()->whereKey($planId)
            ->when($recordId, fn ($query) => $query->where('waste_record_id', $recordId))->exists();
    }

    public function timezone(): string
    {
        return config('hauling-plans.timezone') ?: config('app.timezone');
    }

    public function actualDateLimit(): string
    {
        return today($this->timezone())->toDateString();
    }

    public function cents(mixed $quantity): int
    {
        $value = (string) $quantity;
        if (! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) {
            throw new OperationalConflict('Jumlah harus positif dengan maksimal dua angka desimal, tanpa konversi satuan.');
        }
        $parts = explode('.', $value);

        return ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    /** Total actual commitments plus unused plan allocations, independent of the UI toggle. */
    public function reserved(WasteRecord $record, ?string $ignoreHauling = null, ?string $ignorePlan = null): int
    {
        $actuals = $record->haulings()->whereIn('status', ['pending_approval', 'approved'])
            ->when($ignoreHauling, fn ($q) => $q->whereKeyNot($ignoreHauling))->get();
        $total = $actuals->sum(fn ($row) => $this->cents($row->quantity));
        if (! $this->installed()) {
            return $total;
        }
        foreach (WasteHaulingPlan::query()->where('waste_record_id', $record->id)->whereIn('status', ['pending_approval', 'approved'])
            ->when($ignorePlan, fn ($q) => $q->whereKeyNot($ignorePlan))->get() as $plan) {
            $used = $actuals->where('waste_hauling_plan_id', $plan->id)->sum(fn ($row) => $this->cents($row->quantity));
            $total += max(0, $this->cents($plan->quantity) - $used);
        }

        return $total;
    }

    public function availableFor(WasteRecord $record, ?string $planId = null, ?string $ignoreHauling = null): float
    {
        $available = $this->cents($record->quantity) - $this->reserved($record, $ignoreHauling, $planId);
        if ($planId) {
            $plan = WasteHaulingPlan::query()->findOrFail($planId);
            if (auth()->user() && ! auth()->user()->hasPermission('waste_hauling.view_all') && $plan->created_by !== auth()->id() && ! $ignoreHauling && ! $this->canOperatePlan($planId, $record->id)) {
                abort(403);
            }
            if ($plan->waste_record_id !== $record->id || $plan->status !== 'approved' || $plan->unit !== $record->unit) {
                throw new OperationalConflict('Realisasi harus mengacu pada rencana disetujui untuk catatan dan satuan yang sama.');
            }
            $used = $plan->haulings()->whereIn('status', ['pending_approval', 'approved'])
                ->when($ignoreHauling, fn ($q) => $q->whereKeyNot($ignoreHauling))->get()->sum(fn ($row) => $this->cents($row->quantity));
            $available = min($available, $this->cents($plan->quantity) - $used);
        }

        return max(0, $available) / 100;
    }

    public function summary(WasteRecord $record): array
    {
        $actuals = $record->haulings()->whereIn('status', ['pending_approval', 'approved'])->get();
        $actualReserved = $actuals->sum(fn ($row) => $this->cents($row->quantity));

        return ['pending_actual_quantity' => $actuals->where('status', 'pending_approval')->sum(fn ($row) => $this->cents($row->quantity)) / 100,
            'plan_reserved_quantity' => max(0, $this->reserved($record) - $actualReserved) / 100,
            'available_quantity' => $this->availableFor($record)];
    }

    /**
     * Read-only allocation summaries for a set of records, without altering write-time locking.
     *
     * @param  \Illuminate\Support\Collection<int, WasteRecord>  $records
     * @return array<string, array{reserved_quantity: float, pending_actual_quantity: float, plan_reserved_quantity: float, available_quantity: float}>
     */
    public function summaries(\Illuminate\Support\Collection $records): array
    {
        if ($records->isEmpty()) {
            return [];
        }

        $ids = $records->map(fn (WasteRecord $record) => $record->getKey())->all();
        $installed = $this->installed();
        $actuals = WasteHauling::query()->whereIn('waste_record_id', $ids)
            ->whereIn('status', ['pending_approval', 'approved'])
            ->get($installed ? ['id', 'waste_record_id', 'waste_hauling_plan_id', 'quantity', 'status'] : ['id', 'waste_record_id', 'quantity', 'status'])
            ->groupBy('waste_record_id');
        $plans = $installed
            ? WasteHaulingPlan::query()->whereIn('waste_record_id', $ids)
                ->whereIn('status', ['pending_approval', 'approved'])
                ->get(['id', 'waste_record_id', 'quantity'])->groupBy('waste_record_id')
            : collect();
        $result = [];
        foreach ($records as $record) {
            $rows = $actuals->get($record->id, collect());
            $actualReserved = $rows->sum(fn ($row) => $this->cents($row->quantity));
            $pending = $rows->where('status', 'pending_approval')->sum(fn ($row) => $this->cents($row->quantity));
            $planReserved = 0;
            foreach ($plans->get($record->id, collect()) as $plan) {
                $used = $rows->where('waste_hauling_plan_id', $plan->id)->sum(fn ($row) => $this->cents($row->quantity));
                $planReserved += max(0, $this->cents($plan->quantity) - $used);
            }
            $reserved = $actualReserved + $planReserved;
            $result[(string) $record->id] = [
                'reserved_quantity' => $reserved / 100,
                'pending_actual_quantity' => $pending / 100,
                'plan_reserved_quantity' => $planReserved / 100,
                'available_quantity' => max(0, $this->cents($record->quantity) - $reserved) / 100,
            ];
        }

        return $result;
    }

    public function canViewPlan(WasteHauling $hauling): bool
    {
        if (! $hauling->waste_hauling_plan_id || ! $this->enabled()) {
            return false;
        }
        $user = auth()->user();

        return $user && ($user->hasPermission('waste_hauling.view_all') || $user->hasPermission('waste_hauling.approve') || $user->hasPermission('waste_hauling.reject') || ($user->hasPermission('waste_hauling.view_own') && $hauling->haulingPlan?->created_by === $user->id) || $this->canOperatePlan($hauling->waste_hauling_plan_id));
    }

    public function before(Model $model, ?Model $current): void
    {
        if (! $model instanceof WasteRecord && ! $model instanceof WasteHauling && ! $model instanceof WasteHaulingPlan) {
            return;
        }
        if (! $this->installed()) {
            return;
        }
        if ($model instanceof WasteHaulingPlan || ($model instanceof WasteHauling && $model->waste_hauling_plan_id)) {
            $this->cents($model->getAttributes()['quantity']);
            if ($current && $model->isDirty('deleted_at') && $model->deleted_at !== null) {
                throw new OperationalConflict('Riwayat rencana dan realisasi tidak dapat dihapus.');
            }
        }
        if ($model instanceof WasteHaulingPlan) {
            $record = WasteRecord::query()->findOrFail($model->waste_record_id);
            if ($current && $model->isDirty(['waste_record_id', 'quantity', 'unit', 'planned_date', 'created_by', 'submitted_by'])) {
                throw new OperationalConflict('Rencana yang diajukan tidak dapat diubah; tolak dan buat rencana baru.');
            }
            if (! $current && $model->status !== 'pending_approval') {
                throw new OperationalConflict('Rencana baru wajib diajukan untuk persetujuan.');
            }
            if ($current && $model->isDirty('status') && ! (($current->status === 'pending_approval' && in_array($model->status, ['approved', 'rejected'], true)) || ($current->status === 'approved' && $model->status === 'closed'))) {
                throw new OperationalConflict('Transisi status rencana tidak valid.');
            }
            if ($current && $model->isDirty('status') && in_array($model->status, ['approved', 'rejected', 'closed'], true) && auth()->user() && ! app(ApprovalSeparation::class)->canApprove(auth()->user(), $current)) {
                throw new OperationalConflict('Pengajuan sendiri harus ditinjau supervisor lain.');
            }
            if ($model->status === 'closed' && $model->haulings()->pendingApproval()->exists()) {
                throw new OperationalConflict('Selesaikan persetujuan realisasi sebelum menutup sisa rencana.');
            }
            if ($model->status === 'closed' && ! trim((string) $model->closure_reason)) {
                throw new OperationalConflict('Alasan penutupan wajib diisi.');
            }
            if (in_array($model->status, ['pending_approval', 'approved'], true)) {
                $used = $model->exists ? $model->haulings()->whereIn('status', ['approved', 'pending_approval'])->get()->sum(fn ($row) => $this->cents($row->quantity)) : 0;
                $remaining = max(0, $this->cents($model->quantity) - $used);
                if (! $record->isApproved() || $model->unit !== $record->unit || $this->cents($model->quantity) < 1) {
                    throw new OperationalConflict('Rencana harus memakai catatan limbah yang disetujui, satuan yang sama, dan jumlah minimal 0,01.');
                }
                $available = $this->cents($record->quantity) - $this->reserved($record, null, $model->id);
                if ($remaining > $available) {
                    throw new OperationalConflict('Rencana tidak dapat diproses: jumlah melebihi sisa yang dapat dialokasikan untuk batch '.$record->record_number.' ('.number_format(max(0, $available) / 100, 2, ',', '.').' '.$record->unit.'). Periksa jumlah, batch, dan satuannya; alokasi rencana lain serta realisasi aktif sudah diperhitungkan.');
                }
            }
        }
        if ($model instanceof WasteHauling && $current?->waste_hauling_plan_id && $model->isDirty('waste_hauling_plan_id')) {
            throw new OperationalConflict('Hubungan realisasi dengan rencana tidak dapat diubah.');
        }
        if ($model instanceof WasteHauling && $model->waste_hauling_plan_id) {
            if ($current && $model->isDirty(['waste_record_id', 'waste_hauling_plan_id', 'quantity', 'unit', 'hauling_date', 'created_by', 'submitted_by'])) {
                throw new OperationalConflict('Realisasi yang diajukan tidak dapat diubah.');
            }
            if (! $current && $model->status !== 'pending_approval') {
                throw new OperationalConflict('Realisasi baru wajib menunggu persetujuan.');
            }
            if ($current && $model->isDirty('status') && ! ($current->status === 'pending_approval' && in_array($model->status, ['approved', 'rejected', 'cancelled'], true))) {
                throw new OperationalConflict('Transisi realisasi tidak valid.');
            }
            if ($model->hauling_date->toDateString() > $this->actualDateLimit()) {
                throw new OperationalConflict('Tanggal realisasi tidak boleh setelah '.$this->actualDateLimit().' ('.$this->timezone().').');
            }
            if ($current && $model->isDirty('status') && $model->status === 'rejected' && auth()->user() && ! app(ApprovalSeparation::class)->canApprove(auth()->user(), $current)) {
                throw new OperationalConflict('Pengajuan sendiri harus ditinjau supervisor lain; gunakan pembatalan untuk menarik pengajuan.');
            }
            if ($current?->status === 'approved' && $model->isDirty()) {
                throw new OperationalConflict('Realisasi yang disetujui tidak dapat diubah.');
            }
            $record = WasteRecord::query()->findOrFail($model->waste_record_id);
            if ($model->hauling_date->toDateString() < $record->date->toDateString()) {
                throw new OperationalConflict('Tanggal realisasi tidak boleh sebelum tanggal limbah masuk.');
            }
            if (in_array($model->status, ['pending_approval', 'approved'], true) && (! $record->isApproved() || $model->unit !== $record->unit || $this->cents($model->quantity) < 1 || $this->cents($model->quantity) > (int) round($this->availableFor($record, $model->waste_hauling_plan_id, $model->id) * 100))) {
                throw new OperationalConflict('Jumlah realisasi melebihi sisa rencana atau stok yang tersedia.');
            }
        }
        if ($model instanceof WasteHauling && ! $model->waste_hauling_plan_id && in_array($model->status, ['pending_approval', 'approved'], true)
            && WasteHaulingPlan::query()->where('waste_record_id', $model->waste_record_id)->exists()) {
            $record = WasteRecord::query()->findOrFail($model->waste_record_id);
            if (! $record->isApproved() || $model->unit !== $record->unit || $this->cents($model->quantity) < 1 || $this->cents($model->quantity) > (int) round($this->availableFor($record, null, $model->id) * 100)) {
                throw new OperationalConflict('Jumlah pengangkutan melebihi kapasitas yang belum dialokasikan.');
            }
        }
        if ($model instanceof WasteRecord && $current && $model->isDirty(['status', 'quantity', 'unit', 'date'])) {
            if (WasteHaulingPlan::query()->where('waste_record_id', $model->id)->exists()) {
                throw new OperationalConflict('Catatan dengan rencana angkut tidak dapat diubah.');
            }
        }
    }

    public function beforeDelete(Model $model): void
    {
        if ($model instanceof WasteHaulingPlan || ($model instanceof WasteHauling && $model->waste_hauling_plan_id)
            || ($model instanceof WasteRecord && $this->installed() && WasteHaulingPlan::withTrashed()->where('waste_record_id', $model->id)->exists())) {
            throw new OperationalConflict('Riwayat rencana dan realisasi tidak dapat dihapus; gunakan penolakan atau penutupan sisa.');
        }
    }
}
