<?php

use App\Http\Controllers\WasteManagement\DashboardController;
use App\Http\Controllers\WasteManagement\FabaAdjustmentsController;
use App\Http\Controllers\WasteManagement\FabaDashboardController;
use App\Http\Controllers\WasteManagement\FabaMonthlyApprovalsController;
use App\Http\Controllers\WasteManagement\FabaMovementApprovalsController;
use App\Http\Controllers\WasteManagement\FabaProductionMovementsController;
use App\Http\Controllers\WasteManagement\FabaRecapsController;
use App\Http\Controllers\WasteManagement\FabaReportsController;
use App\Http\Controllers\WasteManagement\FabaUtilizationMovementsController;
use App\Http\Controllers\WasteManagement\MasterData\CategoriesController;
use App\Http\Controllers\WasteManagement\MasterData\CharacteristicsController;
use App\Http\Controllers\WasteManagement\MasterData\TypesController;
use App\Http\Controllers\WasteManagement\MasterData\VendorsController;
use App\Http\Controllers\WasteManagement\WasteHaulingsController;
use App\Http\Controllers\WasteManagement\WasteRecordsController;
use Illuminate\Support\Facades\Route;

// Waste Management Routes
Route::middleware(['auth', 'verified'])->prefix('waste-management')->name('waste-management.')->group(function () {

    // Dashboard
    Route::middleware(['permission:dashboard.view'])
        ->get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::prefix('faba')->name('faba.')->group(function () {
        Route::middleware(['permission:faba_dashboard.view'])
            ->get('/dashboard', [FabaDashboardController::class, 'index'])
            ->name('dashboard');

        Route::prefix('production')->name('production.')->group(function () {
            Route::middleware(['permission:faba_production.view'])
                ->get('/', [FabaProductionMovementsController::class, 'index'])
                ->name('index');
            Route::middleware(['permission:faba_production.create'])
                ->get('/create', [FabaProductionMovementsController::class, 'create'])
                ->name('create');
            Route::middleware(['permission:faba_production.create'])
                ->post('/', [FabaProductionMovementsController::class, 'store'])
                ->name('store');
            Route::middleware(['permission:faba_production.view'])
                ->get('/export/csv', [FabaProductionMovementsController::class, 'exportCsv'])
                ->name('export.csv');
            Route::middleware(['permission:faba_production.view'])
                ->get('/{production}', [FabaProductionMovementsController::class, 'show'])
                ->name('show');
            Route::middleware(['permission:faba_production.edit'])
                ->get('/{production}/edit', [FabaProductionMovementsController::class, 'edit'])
                ->name('edit');
            Route::middleware(['permission:faba_production.edit'])
                ->put('/{production}', [FabaProductionMovementsController::class, 'update'])
                ->name('update');
            Route::middleware(['permission:faba_production.delete'])
                ->delete('/{production}', [FabaProductionMovementsController::class, 'destroy'])
                ->name('destroy');
        });

        Route::prefix('utilization')->name('utilization.')->group(function () {
            Route::middleware(['permission:faba_utilization.view'])
                ->get('/', [FabaUtilizationMovementsController::class, 'index'])
                ->name('index');
            Route::middleware(['permission:faba_utilization.create'])
                ->get('/create', [FabaUtilizationMovementsController::class, 'create'])
                ->name('create');
            Route::middleware(['permission:faba_utilization.create'])
                ->post('/', [FabaUtilizationMovementsController::class, 'store'])
                ->name('store');
            Route::middleware(['permission:faba_utilization.view'])
                ->get('/export/csv', [FabaUtilizationMovementsController::class, 'exportCsv'])
                ->name('export.csv');
            Route::middleware(['permission:faba_utilization.view'])
                ->get('/{utilization}', [FabaUtilizationMovementsController::class, 'show'])
                ->name('show');
            Route::middleware(['permission:faba_utilization.edit'])
                ->get('/{utilization}/edit', [FabaUtilizationMovementsController::class, 'edit'])
                ->name('edit');
            Route::middleware(['permission:faba_utilization.edit'])
                ->put('/{utilization}', [FabaUtilizationMovementsController::class, 'update'])
                ->name('update');
            Route::middleware(['permission:faba_utilization.delete'])
                ->delete('/{utilization}', [FabaUtilizationMovementsController::class, 'destroy'])
                ->name('destroy');
        });

        Route::prefix('adjustments')->name('adjustments.')->group(function () {
            Route::middleware(['permission:faba_adjustments.view'])
                ->get('/', [FabaAdjustmentsController::class, 'index'])
                ->name('index');
            Route::middleware(['permission:faba_adjustments.create'])
                ->get('/create', [FabaAdjustmentsController::class, 'create'])
                ->name('create');
            Route::middleware(['permission:faba_adjustments.create'])
                ->post('/', [FabaAdjustmentsController::class, 'store'])
                ->name('store');
            Route::middleware(['permission:faba_adjustments.view'])
                ->get('/{adjustment}', [FabaAdjustmentsController::class, 'show'])
                ->name('show');
            Route::middleware(['permission:faba_adjustments.edit'])
                ->get('/{adjustment}/edit', [FabaAdjustmentsController::class, 'edit'])
                ->name('edit');
            Route::middleware(['permission:faba_adjustments.edit'])
                ->put('/{adjustment}', [FabaAdjustmentsController::class, 'update'])
                ->name('update');
            Route::middleware(['permission:faba_adjustments.delete'])
                ->delete('/{adjustment}', [FabaAdjustmentsController::class, 'destroy'])
                ->name('destroy');
        });

        Route::prefix('recaps')->name('recaps.')->group(function () {
            Route::middleware(['permission:faba_recaps.view'])
                ->get('/monthly', [FabaRecapsController::class, 'monthly'])
                ->name('monthly');
            Route::middleware(['permission:faba_recaps.view'])
                ->get('/yearly', [FabaRecapsController::class, 'yearly'])
                ->name('yearly');
            Route::middleware(['permission:faba_recaps.view'])
                ->get('/vendors', [FabaRecapsController::class, 'vendors'])
                ->name('vendors');
            Route::middleware(['permission:faba_recaps.view'])
                ->get('/balance', [FabaRecapsController::class, 'balance'])
                ->name('balance');
            Route::middleware(['permission:faba_recaps.view'])
                ->get('/stock-card', [FabaRecapsController::class, 'stockCard'])
                ->name('stockCard');
            Route::middleware(['permission:faba_opening_balance.manage'])
                ->post('/opening-balance', [FabaRecapsController::class, 'storeOpeningBalance'])
                ->name('openingBalance.store');
            Route::middleware(['permission:faba_opening_balance.manage'])
                ->post('/tps-capacity', [FabaRecapsController::class, 'storeTpsCapacity'])
                ->name('tpsCapacity.store');
        });

        Route::prefix('approvals')->name('approvals.')->group(function () {
            Route::middleware(['permission:faba_approvals.view'])
                ->get('/', [FabaMonthlyApprovalsController::class, 'index'])
                ->name('index');
            Route::middleware(['permission:faba_approvals.view'])
                ->get('/history', [FabaMonthlyApprovalsController::class, 'history'])
                ->name('history');
            Route::middleware(['permission:faba_approvals.view'])
                ->get('/{year}/{month}', [FabaMonthlyApprovalsController::class, 'review'])
                ->name('review');
            Route::middleware(['permission:faba_approvals.submit'])
                ->post('/submit', [FabaMonthlyApprovalsController::class, 'submit'])
                ->name('submit');
            Route::middleware(['permission:faba_approvals.approve'])
                ->post('/{year}/{month}/approve', [FabaMonthlyApprovalsController::class, 'approve'])
                ->name('approve');
            Route::middleware(['permission:faba_approvals.reject'])
                ->post('/{year}/{month}/reject', [FabaMonthlyApprovalsController::class, 'reject'])
                ->name('reject');
            Route::middleware(['permission:faba_approvals.reopen'])
                ->post('/{year}/{month}/reopen', [FabaMonthlyApprovalsController::class, 'reopen'])
                ->name('reopen');
        });

        Route::prefix('movements')->name('movements.')->group(function () {
            Route::middleware(['permission:faba_approvals.approve'])
                ->post('/{movement}/approve', [FabaMovementApprovalsController::class, 'approve'])
                ->name('approve');
            Route::middleware(['permission:faba_approvals.reject'])
                ->post('/{movement}/reject', [FabaMovementApprovalsController::class, 'reject'])
                ->name('reject');
        });

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::middleware(['permission:faba_reports.export'])
                ->get('/', [FabaReportsController::class, 'index'])
                ->name('index');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/monthly.xlsx', [FabaReportsController::class, 'monthlyXlsx'])
                ->name('monthly.xlsx');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/monthly.pdf', [FabaReportsController::class, 'monthlyPdf'])
                ->name('monthly.pdf');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/yearly.xlsx', [FabaReportsController::class, 'yearlyXlsx'])
                ->name('yearly.xlsx');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/yearly.pdf', [FabaReportsController::class, 'yearlyPdf'])
                ->name('yearly.pdf');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/vendors.xlsx', [FabaReportsController::class, 'vendorsXlsx'])
                ->name('vendors.xlsx');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/vendors.pdf', [FabaReportsController::class, 'vendorsPdf'])
                ->name('vendors.pdf');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/internal-destinations.xlsx', [FabaReportsController::class, 'internalDestinationsXlsx'])
                ->name('internal-destinations.xlsx');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/internal-destinations.pdf', [FabaReportsController::class, 'internalDestinationsPdf'])
                ->name('internal-destinations.pdf');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/purposes.xlsx', [FabaReportsController::class, 'purposesXlsx'])
                ->name('purposes.xlsx');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/purposes.pdf', [FabaReportsController::class, 'purposesPdf'])
                ->name('purposes.pdf');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/stock-card.xlsx', [FabaReportsController::class, 'stockCardXlsx'])
                ->name('stock-card.xlsx');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/stock-card.pdf', [FabaReportsController::class, 'stockCardPdf'])
                ->name('stock-card.pdf');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/anomalies.xlsx', [FabaReportsController::class, 'anomaliesXlsx'])
                ->name('anomalies.xlsx');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/anomalies.pdf', [FabaReportsController::class, 'anomaliesPdf'])
                ->name('anomalies.pdf');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/analysis-matrix.xlsx', [FabaReportsController::class, 'analysisMatrixXlsx'])
                ->name('analysis-matrix.xlsx');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/analysis-matrix.pdf', [FabaReportsController::class, 'analysisMatrixPdf'])
                ->name('analysis-matrix.pdf');
        });
    });

    // Master Data Routes
    Route::prefix('master-data')->name('master-data.')->group(function () {
        // Categories
        Route::middleware(['permission:waste_categories.view'])
            ->get('/categories', [CategoriesController::class, 'index'])
            ->name('categories.index');

        Route::middleware(['permission:waste_categories.create'])
            ->post('/categories', [CategoriesController::class, 'store'])
            ->name('categories.store');

        Route::middleware(['permission:waste_categories.edit'])
            ->put('/categories/{category}', [CategoriesController::class, 'update'])
            ->name('categories.update');

        Route::middleware(['permission:waste_categories.delete'])
            ->delete('/categories/{category}', [CategoriesController::class, 'destroy'])
            ->name('categories.destroy');

        // Characteristics
        Route::middleware(['permission:waste_characteristics.view'])
            ->get('/characteristics', [CharacteristicsController::class, 'index'])
            ->name('characteristics.index');

        Route::middleware(['permission:waste_characteristics.create'])
            ->post('/characteristics', [CharacteristicsController::class, 'store'])
            ->name('characteristics.store');

        Route::middleware(['permission:waste_characteristics.edit'])
            ->put('/characteristics/{characteristic}', [CharacteristicsController::class, 'update'])
            ->name('characteristics.update');

        Route::middleware(['permission:waste_characteristics.delete'])
            ->delete('/characteristics/{characteristic}', [CharacteristicsController::class, 'destroy'])
            ->name('characteristics.destroy');

        // Waste Types
        Route::middleware(['permission:waste_types.view'])
            ->get('/types', [TypesController::class, 'index'])
            ->name('types.index');

        Route::middleware(['permission:waste_types.create'])
            ->post('/types', [TypesController::class, 'store'])
            ->name('types.store');

        Route::middleware(['permission:waste_types.edit'])
            ->put('/types/{wasteType}', [TypesController::class, 'update'])
            ->name('types.update');

        Route::middleware(['permission:waste_types.delete'])
            ->delete('/types/{wasteType}', [TypesController::class, 'destroy'])
            ->name('types.destroy');

        // Vendors
        Route::middleware(['permission:vendors.view'])
            ->get('/vendors', [VendorsController::class, 'index'])
            ->name('vendors.index');

        Route::middleware(['permission:vendors.create'])
            ->post('/vendors', [VendorsController::class, 'store'])
            ->name('vendors.store');

        Route::middleware(['permission:vendors.edit'])
            ->put('/vendors/{vendor}', [VendorsController::class, 'update'])
            ->name('vendors.update');

        Route::middleware(['permission:vendors.delete'])
            ->delete('/vendors/{vendor}', [VendorsController::class, 'destroy'])
            ->name('vendors.destroy');
    });

    // Waste Records Routes
    Route::prefix('records')->name('records.')->group(function () {
        // Index
        Route::middleware(['permission:waste_records.view_all|waste_records.view_own'])
            ->get('/', [WasteRecordsController::class, 'index'])
            ->name('index');

        // Create
        Route::middleware(['permission:waste_records.create'])
            ->get('/create', [WasteRecordsController::class, 'create'])
            ->name('create');

        Route::middleware(['permission:waste_records.create'])
            ->post('/', [WasteRecordsController::class, 'store'])
            ->name('store');

        // Pending Approval
        Route::middleware(['permission:waste_records.approve|waste_records.reject'])
            ->get('/pending-approval', [WasteRecordsController::class, 'pendingApproval'])
            ->name('pending-approval');

        // Export
        Route::middleware(['permission:waste_records.view_all|waste_records.view_own'])
            ->get('/export/csv', [WasteRecordsController::class, 'exportCsv'])
            ->name('export.csv');

        // Show
        Route::middleware(['permission:waste_records.view_all|waste_records.view_own'])
            ->get('/{wasteRecord}', [WasteRecordsController::class, 'show'])
            ->name('show');

        // Edit
        Route::middleware(['permission:waste_records.edit_own|waste_records.edit_all'])
            ->get('/{wasteRecord}/edit', [WasteRecordsController::class, 'edit'])
            ->name('edit');

        Route::middleware(['permission:waste_records.edit_own|waste_records.edit_all'])
            ->put('/{wasteRecord}', [WasteRecordsController::class, 'update'])
            ->name('update');

        // Delete
        Route::middleware(['permission:waste_records.delete'])
            ->delete('/{wasteRecord}', [WasteRecordsController::class, 'destroy'])
            ->name('destroy');

        // Workflow Actions
        Route::middleware(['permission:waste_records.submit'])
            ->post('/{wasteRecord}/submit', [WasteRecordsController::class, 'submit'])
            ->name('submit');

        Route::middleware(['permission:waste_records.approve'])
            ->post('/{wasteRecord}/approve', [WasteRecordsController::class, 'approve'])
            ->name('approve');

        Route::middleware(['permission:waste_records.reject'])
            ->post('/{wasteRecord}/reject', [WasteRecordsController::class, 'reject'])
            ->name('reject');

        Route::middleware(['permission:waste_records.submit'])
            ->post('/{wasteRecord}/return-to-draft', [WasteRecordsController::class, 'returnToDraft'])
            ->name('return-to-draft');
    });

    Route::prefix('haulings')->name('haulings.')->group(function () {
        Route::middleware(['permission:waste_hauling.view_all|waste_hauling.view_own'])
            ->get('/', [WasteHaulingsController::class, 'index'])
            ->name('index');

        Route::middleware(['permission:waste_hauling.create'])
            ->get('/create', [WasteHaulingsController::class, 'create'])
            ->name('create');

        Route::middleware(['permission:waste_hauling.submit'])
            ->post('/', [WasteHaulingsController::class, 'store'])
            ->name('store');

        Route::middleware(['permission:waste_hauling.approve|waste_hauling.reject'])
            ->get('/pending-approval', [WasteHaulingsController::class, 'pendingApproval'])
            ->name('pending-approval');

        Route::middleware(['permission:waste_hauling.view_all|waste_hauling.view_own|waste_hauling.approve|waste_hauling.reject'])
            ->get('/{wasteHauling}', [WasteHaulingsController::class, 'show'])
            ->name('show');

        Route::middleware(['permission:waste_hauling.approve'])
            ->post('/{wasteHauling}/approve', [WasteHaulingsController::class, 'approve'])
            ->name('approve');

        Route::middleware(['permission:waste_hauling.reject'])
            ->post('/{wasteHauling}/reject', [WasteHaulingsController::class, 'reject'])
            ->name('reject');

        Route::middleware(['permission:waste_hauling.cancel'])
            ->post('/{wasteHauling}/cancel', [WasteHaulingsController::class, 'cancel'])
            ->name('cancel');
    });
});
