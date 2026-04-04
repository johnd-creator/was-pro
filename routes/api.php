<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\FabaAdjustmentController;
use App\Http\Controllers\Api\FabaApprovalController;
use App\Http\Controllers\Api\FabaOptionsController;
use App\Http\Controllers\Api\FabaProductionController;
use App\Http\Controllers\Api\FabaRecapController;
use App\Http\Controllers\Api\FabaUtilizationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\WasteRecordController;
use App\Http\Controllers\Api\WasteTransportationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

        Route::middleware(['auth.api'])->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
            Route::get('/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        });
    });

    Route::middleware(['auth.api', 'tenant.schema'])->group(function () {
        Route::get('/me', [BootstrapController::class, 'me'])->name('api.v1.me');
        Route::get('/dashboard', [BootstrapController::class, 'dashboard'])->name('api.v1.dashboard');
        Route::get('/lookups', [BootstrapController::class, 'lookups'])->name('api.v1.lookups');

        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show'])->name('api.v1.profile.show');
            Route::patch('/', [ProfileController::class, 'update'])->name('api.v1.profile.update');
            Route::put('/password', [ProfileController::class, 'updatePassword'])->name('api.v1.profile.password.update');
        });

        Route::prefix('waste-records')->group(function () {
            Route::get('/', [WasteRecordController::class, 'index'])->name('api.v1.waste-records.index');
            Route::post('/', [WasteRecordController::class, 'store'])->name('api.v1.waste-records.store');
            Route::get('/pending-approval', [WasteRecordController::class, 'pendingApproval'])->name('api.v1.waste-records.pending-approval');
            Route::get('/{wasteRecord}', [WasteRecordController::class, 'show'])->name('api.v1.waste-records.show');
            Route::put('/{wasteRecord}', [WasteRecordController::class, 'update'])->name('api.v1.waste-records.update');
            Route::delete('/{wasteRecord}', [WasteRecordController::class, 'destroy'])->name('api.v1.waste-records.destroy');
            Route::post('/{wasteRecord}/submit', [WasteRecordController::class, 'submit'])->name('api.v1.waste-records.submit');
            Route::post('/{wasteRecord}/approve', [WasteRecordController::class, 'approve'])->name('api.v1.waste-records.approve');
            Route::post('/{wasteRecord}/reject', [WasteRecordController::class, 'reject'])->name('api.v1.waste-records.reject');
            Route::post('/{wasteRecord}/return-to-draft', [WasteRecordController::class, 'returnToDraft'])->name('api.v1.waste-records.return-to-draft');
        });

        Route::prefix('transportations')->group(function () {
            Route::get('/', [WasteTransportationController::class, 'index'])->name('api.v1.transportations.index');
            Route::get('/options', [WasteTransportationController::class, 'options'])->name('api.v1.transportations.options');
            Route::post('/', [WasteTransportationController::class, 'store'])->name('api.v1.transportations.store');
            Route::get('/{wasteTransportation}', [WasteTransportationController::class, 'show'])->name('api.v1.transportations.show');
            Route::put('/{wasteTransportation}', [WasteTransportationController::class, 'update'])->name('api.v1.transportations.update');
            Route::delete('/{wasteTransportation}', [WasteTransportationController::class, 'destroy'])->name('api.v1.transportations.destroy');
            Route::post('/{wasteTransportation}/dispatch', [WasteTransportationController::class, 'dispatch'])->name('api.v1.transportations.dispatch');
            Route::post('/{wasteTransportation}/deliver', [WasteTransportationController::class, 'deliver'])->name('api.v1.transportations.deliver');
            Route::post('/{wasteTransportation}/cancel', [WasteTransportationController::class, 'cancel'])->name('api.v1.transportations.cancel');
        });

        Route::prefix('faba')->group(function () {
            Route::get('/options', FabaOptionsController::class)->name('api.v1.faba.options');
            Route::get('/dashboard', [FabaRecapController::class, 'dashboard'])->name('api.v1.faba.dashboard');

            Route::prefix('production')->group(function () {
                Route::get('/', [FabaProductionController::class, 'index'])->name('api.v1.faba.production.index');
                Route::post('/', [FabaProductionController::class, 'store'])->name('api.v1.faba.production.store');
                Route::get('/{production}', [FabaProductionController::class, 'show'])->name('api.v1.faba.production.show');
                Route::put('/{production}', [FabaProductionController::class, 'update'])->name('api.v1.faba.production.update');
                Route::delete('/{production}', [FabaProductionController::class, 'destroy'])->name('api.v1.faba.production.destroy');
            });

            Route::prefix('utilization')->group(function () {
                Route::get('/', [FabaUtilizationController::class, 'index'])->name('api.v1.faba.utilization.index');
                Route::post('/', [FabaUtilizationController::class, 'store'])->name('api.v1.faba.utilization.store');
                Route::get('/{utilization}', [FabaUtilizationController::class, 'show'])->name('api.v1.faba.utilization.show');
                Route::put('/{utilization}', [FabaUtilizationController::class, 'update'])->name('api.v1.faba.utilization.update');
                Route::delete('/{utilization}', [FabaUtilizationController::class, 'destroy'])->name('api.v1.faba.utilization.destroy');
            });

            Route::prefix('adjustments')->group(function () {
                Route::get('/', [FabaAdjustmentController::class, 'index'])->name('api.v1.faba.adjustments.index');
                Route::post('/', [FabaAdjustmentController::class, 'store'])->name('api.v1.faba.adjustments.store');
                Route::get('/{adjustment}', [FabaAdjustmentController::class, 'show'])->name('api.v1.faba.adjustments.show');
                Route::put('/{adjustment}', [FabaAdjustmentController::class, 'update'])->name('api.v1.faba.adjustments.update');
                Route::delete('/{adjustment}', [FabaAdjustmentController::class, 'destroy'])->name('api.v1.faba.adjustments.destroy');
            });

            Route::prefix('recaps')->group(function () {
                Route::get('/monthly', [FabaRecapController::class, 'monthly'])->name('api.v1.faba.recaps.monthly');
                Route::get('/yearly', [FabaRecapController::class, 'yearly'])->name('api.v1.faba.recaps.yearly');
                Route::get('/vendors', [FabaRecapController::class, 'vendors'])->name('api.v1.faba.recaps.vendors');
                Route::get('/balance', [FabaRecapController::class, 'balance'])->name('api.v1.faba.recaps.balance');
                Route::get('/stock-card', [FabaRecapController::class, 'stockCard'])->name('api.v1.faba.recaps.stock-card');
                Route::post('/opening-balance', [FabaRecapController::class, 'storeOpeningBalance'])->name('api.v1.faba.recaps.opening-balance.store');
            });

            Route::prefix('approvals')->group(function () {
                Route::get('/', [FabaApprovalController::class, 'index'])->name('api.v1.faba.approvals.index');
                Route::get('/history', [FabaApprovalController::class, 'history'])->name('api.v1.faba.approvals.history');
                Route::post('/submit', [FabaApprovalController::class, 'submit'])->name('api.v1.faba.approvals.submit');
                Route::get('/{year}/{month}', [FabaApprovalController::class, 'review'])->name('api.v1.faba.approvals.review');
                Route::post('/{year}/{month}/approve', [FabaApprovalController::class, 'approve'])->name('api.v1.faba.approvals.approve');
                Route::post('/{year}/{month}/reject', [FabaApprovalController::class, 'reject'])->name('api.v1.faba.approvals.reject');
                Route::post('/{year}/{month}/reopen', [FabaApprovalController::class, 'reopen'])->name('api.v1.faba.approvals.reopen');
            });
        });
    });
});
