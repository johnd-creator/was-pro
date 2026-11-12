<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\ApproveWasteHaulingRequest;
use App\Http\Requests\WasteManagement\CloseWasteHaulingPlanRequest;
use App\Http\Requests\WasteManagement\RejectWasteHaulingRequest;
use App\Http\Requests\WasteManagement\WasteHaulingPlanRequest;
use App\Models\WasteHaulingPlan;
use App\Models\WasteRecord;
use App\Services\ApprovalSeparation;
use App\Services\HaulingPlans;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WasteHaulingPlansController extends Controller
{
    private function enabled(): void
    {
        abort_unless(app(HaulingPlans::class)->enabled(), 404);
    }

    private function all(): bool
    {
        return auth()->user()->hasPermission('waste_hauling.view_all');
    }

    private function reviewer(): bool
    {
        return auth()->user()->hasPermission('waste_hauling.approve') || auth()->user()->hasPermission('waste_hauling.reject');
    }

    public function index(): Response
    {
        $this->enabled();
        $plans = WasteHaulingPlan::query()->with(['wasteRecord', 'createdBy', 'approvedByUser', 'closedByUser', 'haulings'])
            ->when(! $this->all() && ! $this->reviewer(), fn ($q) => $q->where('created_by', auth()->id()))->latest()->get();
        $records = WasteRecord::query()->with('wasteType')->approved()->when(! $this->all(), fn ($q) => $q->where('created_by', auth()->id()))->orderBy('date')->get();

        return Inertia::render('waste-management/hauling-plans/Index', ['plansEnabled' => true,
            'plans' => $plans->map(fn ($plan) => $this->serialize($plan)),
            'records' => $records->map(fn ($record) => $this->record($record)),
        ]);
    }

    public function store(WasteHaulingPlanRequest $request): RedirectResponse
    {
        $this->enabled();
        $record = WasteRecord::query()->findOrFail($request->validated('waste_record_id'));
        abort_unless($this->all() || $record->created_by === auth()->id(), 403);
        $plan = WasteHaulingPlan::query()->create([...$request->validated(), 'unit' => $record->unit,
            'status' => 'pending_approval', 'created_by' => auth()->id(), 'submitted_by' => auth()->id(), 'submitted_at' => now()]);

        return redirect()->route('waste-management.hauling-plans.show', $plan)->with('success', 'Rencana diajukan. Saldo limbah belum berkurang.');
    }

    public function show(string $wasteHaulingPlan): Response
    {
        $this->enabled();
        $plan = WasteHaulingPlan::query()->findOrFail($wasteHaulingPlan);
        abort_unless($this->all() || $this->reviewer() || $plan->created_by === auth()->id(), 403);

        return Inertia::render('waste-management/hauling-plans/Show', ['plan' => $this->serialize($plan), 'record' => $this->record($plan->wasteRecord)]);
    }

    public function approve(ApproveWasteHaulingRequest $request, string $wasteHaulingPlan): RedirectResponse
    {
        $this->enabled();
        $plan = WasteHaulingPlan::query()->findOrFail($wasteHaulingPlan);
        if (! $plan->approve(auth()->id(), $request->validated('approval_notes'))) {
            return back()->with('error', 'Status rencana telah berubah.');
        }

        return back()->with('success', 'Rencana disetujui. Catat realisasi setelah limbah benar-benar keluar.');
    }

    public function reject(RejectWasteHaulingRequest $request, string $wasteHaulingPlan): RedirectResponse
    {
        $this->enabled();
        $plan = WasteHaulingPlan::query()->findOrFail($wasteHaulingPlan);
        if (! $plan->reject(auth()->id(), $request->validated('rejection_reason'))) {
            return back()->with('error', 'Status rencana telah berubah.');
        }

        return back()->with('success', 'Rencana ditolak; alokasi dilepas.');
    }

    public function close(CloseWasteHaulingPlanRequest $request, string $wasteHaulingPlan): RedirectResponse
    {
        $this->enabled();
        $plan = WasteHaulingPlan::query()->findOrFail($wasteHaulingPlan);
        if (! $plan->close(auth()->id(), $request->validated('closure_reason'))) {
            return back()->with('error', 'Hanya sisa rencana disetujui yang dapat ditutup.');
        }

        return back()->with('success', 'Sisa rencana ditutup. Realisasi sebelumnya tetap tercatat.');
    }

    public function record(WasteRecord $record): array
    {
        return ['waste_type_id' => $record->waste_type_id, 'waste_type' => $record->wasteType?->only(['name', 'code']),
            'id' => $record->id, 'record_number' => $record->record_number, 'unit' => $record->unit, 'quantity' => (float) $record->quantity,
            'remaining_quantity' => $record->getRemainingQuantity(), 'available_quantity' => app(HaulingPlans::class)->availableFor($record)];
    }

    public function serialize(WasteHaulingPlan $plan): array
    {
        $plan->loadMissing(['wasteRecord', 'createdBy', 'approvedByUser', 'closedByUser', 'haulings']);
        $approved = (float) $plan->haulings->where('status', 'approved')->sum('quantity');
        $pending = (float) $plan->haulings->where('status', 'pending_approval')->sum('quantity');
        $separated = app(ApprovalSeparation::class)->canApprove(auth()->user(), $plan);
        $canAct = $this->all() || $plan->created_by === auth()->id();

        return ['id' => $plan->id, 'waste_record_id' => $plan->waste_record_id, 'record_number' => $plan->wasteRecord?->record_number,
            'planned_date' => $plan->planned_date?->toDateString(), 'quantity' => (float) $plan->quantity, 'unit' => $plan->unit,
            'notes' => $plan->notes, 'status' => $plan->status, 'created_by' => $plan->createdBy?->only(['id', 'name']),
            'approved_by' => $plan->approvedByUser?->only(['id', 'name']), 'submitted_at' => $plan->submitted_at?->toIso8601String(),
            'approved_at' => $plan->approved_at?->toIso8601String(), 'approval_notes' => $plan->approval_notes, 'rejection_reason' => $plan->rejection_reason,
            'closed_by' => $plan->closedByUser?->only(['id', 'name']), 'closed_at' => $plan->closed_at?->toIso8601String(), 'closure_reason' => $plan->closure_reason,
            'approved_actual_quantity' => $approved, 'pending_actual_quantity' => $pending,
            'remaining_plan_quantity' => ! in_array($plan->status, ['pending_approval', 'approved'], true) ? 0 : max(0, round((float) $plan->quantity - $approved - $pending, 2)),
            'abilities' => ['can_approve' => $separated && $plan->canBeApproved() && auth()->user()->hasPermission('waste_hauling.approve'),
                'can_reject' => $separated && $plan->canBeApproved() && auth()->user()->hasPermission('waste_hauling.reject'),
                'can_close' => $separated && $plan->canBeClosed() && $pending === 0.0 && auth()->user()->hasPermission('waste_hauling.approve'),
                'can_realize' => $canAct && $plan->status === 'approved' && $approved + $pending < (float) $plan->quantity && auth()->user()->hasPermission('waste_hauling.submit') && auth()->user()->hasPermission('waste_hauling.create')],
            'actuals' => $plan->haulings->map(fn ($row) => ['id' => $row->id, 'can_view' => $this->all() || $this->reviewer() || $row->created_by === auth()->id(), 'hauling_number' => $row->hauling_number, 'hauling_date' => $row->hauling_date?->toDateString(), 'quantity' => (float) $row->quantity, 'unit' => $row->unit, 'status' => $row->status])->all()];
    }
}
