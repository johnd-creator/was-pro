<?php

use App\Http\Controllers\WasteManagement\DashboardController;
use App\Http\Controllers\WasteManagement\FabaDashboardController;
use App\Http\Controllers\WasteManagement\FabaMonthlyApprovalsController;
use App\Http\Controllers\WasteManagement\FabaProductionEntriesController;
use App\Http\Controllers\WasteManagement\FabaRecapsController;
use App\Http\Controllers\WasteManagement\FabaReportsController;
use App\Http\Controllers\WasteManagement\FabaUtilizationEntriesController;
use App\Http\Controllers\WasteManagement\MasterData\CategoriesController;
use App\Http\Controllers\WasteManagement\MasterData\CharacteristicsController;
use App\Http\Controllers\WasteManagement\MasterData\TypesController;
use App\Http\Controllers\WasteManagement\MasterData\VendorsController;
use App\Http\Controllers\WasteManagement\WasteRecordsController;
use App\Http\Controllers\WasteManagement\WasteTransportationsController;
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
                ->get('/', [FabaProductionEntriesController::class, 'index'])
                ->name('index');
            Route::middleware(['permission:faba_production.create'])
                ->get('/create', [FabaProductionEntriesController::class, 'create'])
                ->name('create');
            Route::middleware(['permission:faba_production.create'])
                ->post('/', [FabaProductionEntriesController::class, 'store'])
                ->name('store');
            Route::middleware(['permission:faba_production.view'])
                ->get('/export/csv', [FabaProductionEntriesController::class, 'exportCsv'])
                ->name('export.csv');
            Route::middleware(['permission:faba_production.view'])
                ->get('/{production}', [FabaProductionEntriesController::class, 'show'])
                ->name('show');
            Route::middleware(['permission:faba_production.edit'])
                ->get('/{production}/edit', [FabaProductionEntriesController::class, 'edit'])
                ->name('edit');
            Route::middleware(['permission:faba_production.edit'])
                ->put('/{production}', [FabaProductionEntriesController::class, 'update'])
                ->name('update');
            Route::middleware(['permission:faba_production.delete'])
                ->delete('/{production}', [FabaProductionEntriesController::class, 'destroy'])
                ->name('destroy');
        });

        Route::prefix('utilization')->name('utilization.')->group(function () {
            Route::middleware(['permission:faba_utilization.view'])
                ->get('/', [FabaUtilizationEntriesController::class, 'index'])
                ->name('index');
            Route::middleware(['permission:faba_utilization.create'])
                ->get('/create', [FabaUtilizationEntriesController::class, 'create'])
                ->name('create');
            Route::middleware(['permission:faba_utilization.create'])
                ->post('/', [FabaUtilizationEntriesController::class, 'store'])
                ->name('store');
            Route::middleware(['permission:faba_utilization.view'])
                ->get('/export/csv', [FabaUtilizationEntriesController::class, 'exportCsv'])
                ->name('export.csv');
            Route::middleware(['permission:faba_utilization.view'])
                ->get('/{utilization}', [FabaUtilizationEntriesController::class, 'show'])
                ->name('show');
            Route::middleware(['permission:faba_utilization.edit'])
                ->get('/{utilization}/edit', [FabaUtilizationEntriesController::class, 'edit'])
                ->name('edit');
            Route::middleware(['permission:faba_utilization.edit'])
                ->put('/{utilization}', [FabaUtilizationEntriesController::class, 'update'])
                ->name('update');
            Route::middleware(['permission:faba_utilization.delete'])
                ->delete('/{utilization}', [FabaUtilizationEntriesController::class, 'destroy'])
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
                ->post('/opening-balance', [FabaRecapsController::class, 'storeOpeningBalance'])
                ->name('openingBalance.store');
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

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::middleware(['permission:faba_reports.export'])
                ->get('/', [FabaReportsController::class, 'index'])
                ->name('index');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/monthly.csv', [FabaReportsController::class, 'monthlyCsv'])
                ->name('monthly.csv');
            Route::middleware(['permission:faba_reports.export'])
                ->get('/yearly.csv', [FabaReportsController::class, 'yearlyCsv'])
                ->name('yearly.csv');
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

        // Pending Approval
        Route::middleware(['permission:waste_records.approve|waste_records.reject'])
            ->get('/pending-approval', [WasteRecordsController::class, 'pendingApproval'])
            ->name('pending-approval');

        // Export
        Route::middleware(['permission:waste_records.view_all|waste_records.view_own'])
            ->get('/export/csv', [WasteRecordsController::class, 'exportCsv'])
            ->name('export.csv');
    });

    // Waste Transportations Routes
    Route::prefix('transportations')->name('transportations.')->group(function () {
        // Index
        Route::middleware(['permission:transportation.view_all|transportation.view_own'])
            ->get('/', [WasteTransportationsController::class, 'index'])
            ->name('index');

        // Create
        Route::middleware(['permission:transportation.create'])
            ->get('/create', [WasteTransportationsController::class, 'create'])
            ->name('create');

        Route::middleware(['permission:transportation.create'])
            ->post('/', [WasteTransportationsController::class, 'store'])
            ->name('store');

        // Show
        Route::middleware(['permission:transportation.view_all|transportation.view_own'])
            ->get('/{wasteTransportation}', [WasteTransportationsController::class, 'show'])
            ->name('show');

        // Edit
        Route::middleware(['permission:transportation.edit'])
            ->get('/{wasteTransportation}/edit', [WasteTransportationsController::class, 'edit'])
            ->name('edit');

        Route::middleware(['permission:transportation.edit'])
            ->put('/{wasteTransportation}', [WasteTransportationsController::class, 'update'])
            ->name('update');

        // Delete
        Route::middleware(['permission:transportation.delete'])
            ->delete('/{wasteTransportation}', [WasteTransportationsController::class, 'destroy'])
            ->name('destroy');

        // Workflow Actions
        Route::middleware(['permission:transportation.dispatch'])
            ->post('/{wasteTransportation}/dispatch', [WasteTransportationsController::class, 'dispatch'])
            ->name('dispatch');

        Route::middleware(['permission:transportation.deliver'])
            ->post('/{wasteTransportation}/deliver', [WasteTransportationsController::class, 'deliver'])
            ->name('deliver');

        Route::middleware(['permission:transportation.cancel'])
            ->post('/{wasteTransportation}/cancel', [WasteTransportationsController::class, 'cancel'])
            ->name('cancel');

        // Export
        Route::middleware(['permission:transportation.view_all|transportation.view_own'])
            ->get('/export/csv', [WasteTransportationsController::class, 'exportCsv'])
            ->name('export.csv');
    });
});
