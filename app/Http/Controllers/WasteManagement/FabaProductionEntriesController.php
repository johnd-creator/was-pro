<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\FabaProductionEntryRequest;
use App\Models\FabaAuditLog;
use App\Models\FabaProductionEntry;
use App\Services\FabaAuditService;
use App\Services\FabaRecapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FabaProductionEntriesController extends Controller
{
    public function __construct(
        protected FabaRecapService $fabaRecapService,
        protected FabaAuditService $fabaAuditService
    ) {}

    public function index(): Response
    {
        $entries = FabaProductionEntry::query()
            ->with(['createdByUser:id,name', 'updatedByUser:id,name'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FabaProductionEntry $entry): array => $this->transformEntry($entry));

        return Inertia::render('waste-management/faba/production/Index', [
            'entries' => $entries,
            'filters' => [
                'materials' => FabaProductionEntry::materialOptions(),
                'entryTypes' => FabaProductionEntry::entryTypeOptions(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('waste-management/faba/production/Create', [
            'materialOptions' => FabaProductionEntry::materialOptions(),
            'entryTypeOptionsByMaterial' => FabaProductionEntry::entryTypeOptionsByMaterial(),
            'defaultUnit' => FabaProductionEntry::DEFAULT_UNIT,
        ]);
    }

    public function store(FabaProductionEntryRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $year = (int) date('Y', strtotime($validated['transaction_date']));
        $month = (int) date('n', strtotime($validated['transaction_date']));

        if ($this->fabaRecapService->isPeriodLocked($year, $month)) {
            return Redirect::back()
                ->with('error', 'Periode bulan ini sedang diajukan atau sudah disetujui, sehingga transaksi tidak bisa ditambah.');
        }

        $entry = FabaProductionEntry::create([
            ...$validated,
            'entry_number' => $this->generateEntryNumber($validated['transaction_date']),
            'unit' => FabaProductionEntry::DEFAULT_UNIT,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
            'create',
            FabaAuditLog::MODULE_PRODUCTION,
            FabaProductionEntry::class,
            $entry->id,
            $year,
            $month,
            'Transaksi produksi dibuat.',
            $this->transformEntry($entry)
        );

        return Redirect::route('waste-management.faba.production.show', $entry)
            ->with('success', 'Transaksi produksi FABA berhasil dibuat.');
    }

    public function show(string $production): Response
    {
        $production = $this->findProductionEntryOrFail($production);
        $production->load(['createdByUser:id,name', 'updatedByUser:id,name']);

        return Inertia::render('waste-management/faba/production/Show', [
            'entry' => $this->transformEntry($production),
        ]);
    }

    public function edit(string $production): Response
    {
        $production = $this->findProductionEntryOrFail($production);
        $this->abortIfLocked($production);

        return Inertia::render('waste-management/faba/production/Edit', [
            'entry' => $this->transformEntry($production),
            'materialOptions' => FabaProductionEntry::materialOptions(),
            'entryTypeOptionsByMaterial' => FabaProductionEntry::entryTypeOptionsByMaterial(),
            'defaultUnit' => FabaProductionEntry::DEFAULT_UNIT,
        ]);
    }

    public function update(FabaProductionEntryRequest $request, string $production): RedirectResponse
    {
        $production = $this->findProductionEntryOrFail($production);
        $this->abortIfLocked($production);

        $validated = $request->validated();
        $year = (int) date('Y', strtotime($validated['transaction_date']));
        $month = (int) date('n', strtotime($validated['transaction_date']));

        if (
            ($year !== (int) $production->transaction_date->format('Y') || $month !== (int) $production->transaction_date->format('n'))
            && $this->fabaRecapService->isPeriodLocked($year, $month)
        ) {
            return Redirect::back()
                ->with('error', 'Periode tujuan sedang terkunci.');
        }

        $production->update([
            ...$validated,
            'unit' => FabaProductionEntry::DEFAULT_UNIT,
            'updated_by' => Auth::id(),
        ]);

        $this->fabaAuditService->log(
            Auth::id(),
            'update',
            FabaAuditLog::MODULE_PRODUCTION,
            FabaProductionEntry::class,
            $production->id,
            $year,
            $month,
            'Transaksi produksi diperbarui.',
            $this->transformEntry($production->fresh())
        );

        return Redirect::route('waste-management.faba.production.show', $production)
            ->with('success', 'Transaksi produksi FABA berhasil diperbarui.');
    }

    public function destroy(string $production): RedirectResponse
    {
        $production = $this->findProductionEntryOrFail($production);
        $this->abortIfLocked($production);
        $transactionDate = $production->transaction_date;
        $entryData = $this->transformEntry($production);
        $production->delete();

        $this->fabaAuditService->log(
            Auth::id(),
            'delete',
            FabaAuditLog::MODULE_PRODUCTION,
            FabaProductionEntry::class,
            $production->id,
            (int) $transactionDate->format('Y'),
            (int) $transactionDate->format('n'),
            'Transaksi produksi dihapus.',
            $entryData
        );

        return Redirect::route('waste-management.faba.production.index')
            ->with('success', 'Transaksi produksi FABA berhasil dihapus.');
    }

    public function exportCsv(): StreamedResponse
    {
        $year = request('year');
        $month = request('month');
        $materialType = request('material_type');
        $entryType = request('entry_type');

        $entries = FabaProductionEntry::query()
            ->with(['createdByUser:id,name'])
            ->when($year, fn ($query) => $query->whereYear('transaction_date', (int) $year))
            ->when($month, fn ($query) => $query->whereMonth('transaction_date', (int) $month))
            ->when($materialType, fn ($query) => $query->where('material_type', $materialType))
            ->when($entryType, fn ($query) => $query->where('entry_type', $entryType))
            ->orderByDesc('transaction_date')
            ->get();

        return response()->stream(function () use ($entries): void {
            $file = fopen('php://output', 'w');

            if ($file === false) {
                Log::error('Failed to open CSV stream for FABA production export.');
                abort(500, 'Gagal menyiapkan file export produksi FABA.');
            }

            fputcsv($file, [
                'Entry Number',
                'Transaction Date',
                'Material Type',
                'Entry Type',
                'Period',
                'Approval Status',
                'Quantity',
                'Unit',
                'Note',
                'Created By',
                'Created At',
            ]);

            foreach ($entries as $entry) {
                fputcsv($file, [
                    $entry->entry_number,
                    $entry->transaction_date->format('Y-m-d'),
                    $entry->material_type,
                    $entry->entry_type,
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
                    $entry->note,
                    $entry->createdByUser?->name,
                    $entry->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="faba-production-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    protected function transformEntry(FabaProductionEntry $entry): array
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
            'entry_type' => $entry->entry_type,
            'quantity' => (float) $entry->quantity,
            'unit' => $entry->unit,
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

    protected function abortIfLocked(FabaProductionEntry $entry): void
    {
        if ($this->fabaRecapService->isPeriodLocked(
            (int) $entry->transaction_date->format('Y'),
            (int) $entry->transaction_date->format('n'),
        )) {
            abort(403, 'Periode transaksi ini sedang terkunci.');
        }
    }

    protected function findProductionEntryOrFail(string $id): FabaProductionEntry
    {
        return FabaProductionEntry::query()
            ->with(['createdByUser:id,name', 'updatedByUser:id,name'])
            ->findOrFail($id);
    }

    protected function generateEntryNumber(string $transactionDate): string
    {
        $prefix = 'FP-'.date('Ym', strtotime($transactionDate));
        $sequence = FabaProductionEntry::query()
            ->where('entry_number', 'like', $prefix.'-%')
            ->count() + 1;

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
