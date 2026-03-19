<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\ApproveFabaMonthlyApprovalRequest;
use App\Http\Requests\WasteManagement\RejectFabaMonthlyApprovalRequest;
use App\Http\Requests\WasteManagement\ReopenFabaMonthlyApprovalRequest;
use App\Http\Requests\WasteManagement\SubmitFabaMonthlyApprovalRequest;
use App\Models\FabaAuditLog;
use App\Models\FabaMonthlyApproval;
use App\Models\FabaProductionEntry;
use App\Models\FabaUtilizationEntry;
use App\Services\FabaAuditService;
use App\Services\FabaRecapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class FabaMonthlyApprovalsController extends Controller
{
    public function __construct(
        protected FabaRecapService $fabaRecapService,
        protected FabaAuditService $fabaAuditService
    ) {}

    public function index(): Response
    {
        $year = (int) request('year', now()->year);

        return Inertia::render('waste-management/faba/approvals/Index', [
            'year' => $year,
            'periods' => $this->fabaRecapService->getAvailablePeriods($year),
        ]);
    }

    public function review(int $year, int $month): Response
    {
        $approval = $this->fabaRecapService->getMonthlyApproval($year, $month);
        $detail = $this->fabaRecapService->getMonthlyRecapDetail($year, $month);

        return Inertia::render('waste-management/faba/approvals/Review', [
            'approval' => $this->fabaRecapService->getPeriodMeta($year, $month),
            'recap' => $detail['recap'],
            'productionEntries' => $detail['production_entries'],
            'utilizationEntries' => $detail['utilization_entries'],
            'auditLogs' => $detail['audit_logs'],
        ]);
    }

    public function history(): Response
    {
        return Inertia::render('waste-management/faba/approvals/History', [
            'approvals' => FabaMonthlyApproval::query()
                ->with(['submittedByUser:id,name', 'approvedByUser:id,name', 'rejectedByUser:id,name'])
                ->latest('year')
                ->latest('month')
                ->get(),
            'auditLogs' => FabaAuditLog::query()
                ->with('actor:id,name')
                ->latest()
                ->limit(100)
                ->get(),
        ]);
    }

    public function submit(SubmitFabaMonthlyApprovalRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $hasTransactions = FabaProductionEntry::query()->forPeriod($year, $month)->exists()
            || FabaUtilizationEntry::query()->forPeriod($year, $month)->exists();

        if (! $hasTransactions) {
            return Redirect::back()->with('error', 'Periode kosong tidak dapat diajukan untuk approval.');
        }

        $approval = $this->fabaRecapService->getOrCreateMonthlyApproval($year, $month);

        if (! $approval->canSubmit()) {
            return Redirect::back()->with('error', 'Periode ini tidak dapat diajukan lagi.');
        }

        $approval->update([
            'status' => FabaMonthlyApproval::STATUS_SUBMITTED,
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_note' => null,
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
            'submit',
            FabaAuditLog::MODULE_APPROVAL,
            FabaMonthlyApproval::class,
            $approval->id,
            $year,
            $month,
            'Periode diajukan untuk approval.',
            ['status' => FabaMonthlyApproval::STATUS_SUBMITTED]
        );

        return Redirect::route('waste-management.faba.approvals.review', [$year, $month])
            ->with('success', 'Periode berhasil diajukan untuk approval.');
    }

    public function approve(ApproveFabaMonthlyApprovalRequest $request, int $year, int $month): RedirectResponse
    {
        $approval = $this->fabaRecapService->getOrCreateMonthlyApproval($year, $month);

        if (! $approval->canApprove()) {
            return Redirect::back()->with('error', 'Periode ini tidak dapat disetujui.');
        }

        $approval->update([
            'status' => FabaMonthlyApproval::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'submitted_by' => $approval->submitted_by ?: Auth::id(),
            'submitted_at' => $approval->submitted_at ?: now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_note' => null,
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
            'approve',
            FabaAuditLog::MODULE_APPROVAL,
            FabaMonthlyApproval::class,
            $approval->id,
            $year,
            $month,
            'Periode disetujui.',
            ['approval_note' => $request->validated('approval_note')]
        );

        return Redirect::route('waste-management.faba.approvals.review', [$year, $month])
            ->with('success', 'Periode berhasil disetujui.');
    }

    public function reject(RejectFabaMonthlyApprovalRequest $request, int $year, int $month): RedirectResponse
    {
        $approval = $this->fabaRecapService->getOrCreateMonthlyApproval($year, $month);

        if (! $approval->canReject()) {
            return Redirect::back()->with('error', 'Periode ini tidak dapat ditolak.');
        }

        $approval->update([
            'status' => FabaMonthlyApproval::STATUS_REJECTED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'rejection_note' => $request->validated('rejection_note'),
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
            'reject',
            FabaAuditLog::MODULE_APPROVAL,
            FabaMonthlyApproval::class,
            $approval->id,
            $year,
            $month,
            'Periode ditolak.',
            ['rejection_note' => $request->validated('rejection_note')]
        );

        return Redirect::route('waste-management.faba.approvals.review', [$year, $month])
            ->with('success', 'Periode berhasil ditolak.');
    }

    public function reopen(ReopenFabaMonthlyApprovalRequest $request, int $year, int $month): RedirectResponse
    {
        $approval = $this->fabaRecapService->getOrCreateMonthlyApproval($year, $month);

        if (! $approval->canReopen()) {
            return Redirect::back()->with('error', 'Periode ini tidak dapat dibuka kembali.');
        }

        $approval->update([
            'status' => FabaMonthlyApproval::STATUS_REJECTED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'rejection_note' => $request->validated('reopen_note'),
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
            'reopen',
            FabaAuditLog::MODULE_APPROVAL,
            FabaMonthlyApproval::class,
            $approval->id,
            $year,
            $month,
            'Periode dibuka kembali untuk revisi.',
            ['reopen_note' => $request->validated('reopen_note')]
        );

        return Redirect::route('waste-management.faba.approvals.review', [$year, $month])
            ->with('success', 'Periode berhasil dibuka kembali untuk revisi.');
    }
}
