<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\FabaUtilizationEntryRequest;
use App\Models\FabaAuditLog;
use App\Models\FabaUtilizationEntry;
use App\Models\Vendor;
use App\Services\FabaAuditService;
use App\Services\FabaRecapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FabaUtilizationEntriesController extends Controller
{
    public function __construct(
        protected FabaRecapService $fabaRecapService,
        protected FabaAuditService $fabaAuditService
    ) {}

    public function index(): Response
    {
        $entries = FabaUtilizationEntry::query()
            ->with(['vendor:id,name', 'createdByUser:id,name', 'updatedByUser:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FabaUtilizationEntry $entry): array => $this->transformEntry($entry));

        return Inertia::render('waste-management/faba/utilization/Index', [
            'entries' => $entries,
            'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'materials' => FabaUtilizationEntry::materialOptions(),
                'utilizationTypes' => FabaUtilizationEntry::utilizationTypeOptions(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('waste-management/faba/utilization/Create', [
            'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'name']),
            'materialOptions' => FabaUtilizationEntry::materialOptions(),
            'utilizationTypeOptions' => FabaUtilizationEntry::utilizationTypeOptions(),
            'defaultUnit' => FabaUtilizationEntry::DEFAULT_UNIT,
            'requirements' => [
                'external' => [
                    'requiresVendor' => true,
                    'requiresDocument' => true,
                ],
                'internal' => [
                    'requiresVendor' => false,
                    'requiresDocument' => false,
                ],
            ],
        ]);
    }

    public function store(FabaUtilizationEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $year = (int) date('Y', strtotime($validated['transaction_date']));
        $month = (int) date('n', strtotime($validated['transaction_date']));

        if ($this->fabaRecapService->isPeriodLocked($year, $month)) {
            return Redirect::back()
                ->with('error', 'Periode bulan ini sedang diajukan atau sudah disetujui, sehingga transaksi tidak bisa ditambah.');
        }

        if ($request->hasFile('attachment')) {
            $validated['attachment_path'] = $request->file('attachment')->store(
                'faba/utilization-attachments',
                config('filesystems.default')
            );
        }

        unset($validated['attachment']);

        $entry = FabaUtilizationEntry::create([
            ...$validated,
            'entry_number' => $this->generateEntryNumber($validated['transaction_date']),
            'unit' => FabaUtilizationEntry::DEFAULT_UNIT,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
            'create',
            FabaAuditLog::MODULE_UTILIZATION,
            FabaUtilizationEntry::class,
            $entry->id,
            $year,
            $month,
            'Transaksi pemanfaatan dibuat.',
            $this->transformEntry($entry->fresh(['vendor:id,name', 'createdByUser:id,name', 'updatedByUser:id,name']))
        );

        return Redirect::route('waste-management.faba.utilization.show', $entry)
            ->with('success', 'Transaksi pemanfaatan FABA berhasil dibuat.');
    }

    public function show(string $utilization): Response
    {
        $utilization = $this->findUtilizationEntryOrFail($utilization);
        $utilization->load(['vendor:id,name', 'createdByUser:id,name', 'updatedByUser:id,name']);

        return Inertia::render('waste-management/faba/utilization/Show', [
            'entry' => $this->transformEntry($utilization),
        ]);
    }

    public function edit(string $utilization): Response
    {
        $utilization = $this->findUtilizationEntryOrFail($utilization);
        $this->abortIfLocked($utilization);

        return Inertia::render('waste-management/faba/utilization/Edit', [
            'entry' => $this->transformEntry($utilization),
            'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'name']),
            'materialOptions' => FabaUtilizationEntry::materialOptions(),
            'utilizationTypeOptions' => FabaUtilizationEntry::utilizationTypeOptions(),
            'defaultUnit' => FabaUtilizationEntry::DEFAULT_UNIT,
            'requirements' => [
                'external' => [
                    'requiresVendor' => true,
                    'requiresDocument' => true,
                ],
                'internal' => [
                    'requiresVendor' => false,
                    'requiresDocument' => false,
                ],
            ],
        ]);
    }

    public function update(FabaUtilizationEntryRequest $request, string $utilization): RedirectResponse
    {
        $utilization = $this->findUtilizationEntryOrFail($utilization);
        $this->abortIfLocked($utilization);

        $validated = $request->validated();
        $year = (int) date('Y', strtotime($validated['transaction_date']));
        $month = (int) date('n', strtotime($validated['transaction_date']));

        if (
            ($year !== (int) $utilization->transaction_date->format('Y') || $month !== (int) $utilization->transaction_date->format('n'))
            && $this->fabaRecapService->isPeriodLocked($year, $month)
        ) {
            return Redirect::back()->with('error', 'Periode tujuan sedang terkunci.');
        }

        if ($request->hasFile('attachment')) {
            $validated['attachment_path'] = $request->file('attachment')->store(
                'faba/utilization-attachments',
                config('filesystems.default')
            );
        }

        unset($validated['attachment']);

        $utilization->update([
            ...$validated,
            'unit' => FabaUtilizationEntry::DEFAULT_UNIT,
            'updated_by' => Auth::id(),
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
            'update',
            FabaAuditLog::MODULE_UTILIZATION,
            FabaUtilizationEntry::class,
            $utilization->id,
            $year,
            $month,
            'Transaksi pemanfaatan diperbarui.',
            $this->transformEntry($utilization->fresh(['vendor:id,name', 'createdByUser:id,name', 'updatedByUser:id,name']))
        );

        return Redirect::route('waste-management.faba.utilization.show', $utilization)
            ->with('success', 'Transaksi pemanfaatan FABA berhasil diperbarui.');
    }

    public function destroy(string $utilization): RedirectResponse
    {
        $utilization = $this->findUtilizationEntryOrFail($utilization);
        $this->abortIfLocked($utilization);
        $transactionDate = $utilization->transaction_date;
        $entryData = $this->transformEntry($utilization);
        $utilization->delete();

        $this->fabaAuditService->log(
            Auth::id(),
            'delete',
            FabaAuditLog::MODULE_UTILIZATION,
            FabaUtilizationEntry::class,
            $utilization->id,
            (int) $transactionDate->format('Y'),
            (int) $transactionDate->format('n'),
            'Transaksi pemanfaatan dihapus.',
            $entryData
        );

        return Redirect::route('waste-management.faba.utilization.index')
            ->with('success', 'Transaksi pemanfaatan FABA berhasil dihapus.');
    }

    public function exportCsv(): StreamedResponse
    {
        $year = request('year');
        $month = request('month');
        $materialType = request('material_type');
        $utilizationType = request('utilization_type');
        $vendorId = request('vendor_id');

        $entries = FabaUtilizationEntry::query()
            ->with(['vendor:id,name', 'createdByUser:id,name'])
            ->when($year, fn ($query) => $query->whereYear('transaction_date', (int) $year))
            ->when($month, fn ($query) => $query->whereMonth('transaction_date', (int) $month))
            ->when($materialType, fn ($query) => $query->where('material_type', $materialType))
            ->when($utilizationType, fn ($query) => $query->where('utilization_type', $utilizationType))
            ->when($vendorId, fn ($query) => $query->where('vendor_id', $vendorId))
            ->orderByDesc('transaction_date')
            ->get();

        return response()->stream(function () use ($entries): void {
            $file = fopen('php://output', 'w');

            if ($file === false) {
                Log::error('Failed to open CSV stream for FABA utilization export.');
                abort(500, 'Gagal menyiapkan file export pemanfaatan FABA.');
            }

            fputcsv($file, [
                'Entry Number',
                'Transaction Date',
                'Material Type',
                'Utilization Type',
                'Vendor',
                'Period',
                'Approval Status',
                'Quantity',
                'Unit',
                'Document Number',
                'Document Date',
                'Attachment Path',
                'Note',
                'Created By',
                'Created At',
            ]);

            foreach ($entries as $entry) {
                fputcsv($file, [
                    $entry->entry_number,
                    $entry->transaction_date->format('Y-m-d'),
                    $entry->material_type,
                    $entry->utilization_type,
                    $entry->vendor?->name,
                    $this->fabaRecapService->formatPeriodLabel(
                        (int) $entry->transaction_date->format('Y'),
                        (int) $entry->transaction_date->format('n'),
                    ),
                    $this->fabaRecapService->getPeriodStatus(
                        (int) $entry->transaction_date->format('Y'),
                        (int) $entry->transaction_date->format('n'),
                    ),
                    $entry->quantity,
                    $entry->unit,
                    $entry->document_number,
                    $entry->document_date?->format('Y-m-d'),
                    $entry->attachment_path,
                    $entry->note,
                    $entry->createdByUser?->name,
                    $entry->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="faba-utilization-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    protected function transformEntry(FabaUtilizationEntry $entry): array
    {
        $approval = $this->fabaRecapService->getMonthlyApproval(
            (int) $entry->transaction_date->format('Y'),
            (int) $entry->transaction_date->format('n'),
        );

        return [
            'id' => $entry->id,
            'entry_number' => $entry->entry_number,
            'transaction_date' => $entry->transaction_date->format('Y-m-d'),
            'material_type' => $entry->material_type,
            'utilization_type' => $entry->utilization_type,
            'vendor_id' => $entry->vendor_id,
            'vendor' => $entry->vendor,
            'quantity' => (float) $entry->quantity,
            'unit' => $entry->unit,
            'document_number' => $entry->document_number,
            'document_date' => $entry->document_date?->format('Y-m-d'),
            'attachment_path' => $entry->attachment_path,
            'note' => $entry->note,
            'created_by_user' => $entry->createdByUser,
            'updated_by_user' => $entry->updatedByUser,
            'created_at' => $entry->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $entry->updated_at?->format('Y-m-d H:i:s'),
            'approval_status' => $approval?->status ?? 'draft',
            'period_label' => $this->fabaRecapService->formatPeriodLabel(
                (int) $entry->transaction_date->format('Y'),
                (int) $entry->transaction_date->format('n'),
            ),
        ];
    }

    protected function abortIfLocked(FabaUtilizationEntry $entry): void
    {
        if ($this->fabaRecapService->isPeriodLocked(
            (int) $entry->transaction_date->format('Y'),
            (int) $entry->transaction_date->format('n'),
        )) {
            abort(403, 'Periode transaksi ini sedang terkunci.');
        }
    }

    protected function findUtilizationEntryOrFail(string $id): FabaUtilizationEntry
    {
        return FabaUtilizationEntry::query()
            ->with(['vendor:id,name', 'createdByUser:id,name', 'updatedByUser:id,name'])
            ->findOrFail($id);
    }

    protected function generateEntryNumber(string $transactionDate): string
    {
        $prefix = 'FU-'.date('Ym', strtotime($transactionDate));
        $sequence = FabaUtilizationEntry::query()
            ->where('entry_number', 'like', $prefix.'-%')
            ->count() + 1;

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
