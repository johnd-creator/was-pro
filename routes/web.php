<?php

use App\Http\Controllers\Admin\OrganizationsController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});

// Admin routes
Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    // Organizations management (Super Admin only)
    Route::middleware(['super.admin'])->group(function () {
        Route::get('/organizations', [OrganizationsController::class, 'index'])->name('organizations.index');
        Route::post('/organizations', [OrganizationsController::class, 'store'])->name('organizations.store');
        Route::put('/organizations/{organization}', [OrganizationsController::class, 'update'])->name('organizations.update');
        Route::delete('/organizations/{organization}', [OrganizationsController::class, 'destroy'])->name('organizations.destroy');
    });

    // Users management (Super Admin and Admin)
    Route::middleware(['permission:users.view'])->group(function () {
        Route::get('/users', [UsersController::class, 'index'])->name('users.index');
        Route::middleware(['permission:users.create'])->group(function () {
            Route::post('/users', [UsersController::class, 'store'])->name('users.store');
        });
        Route::middleware(['permission:users.edit'])->group(function () {
            Route::put('/users/{user}', [UsersController::class, 'update'])->name('users.update');
            Route::put('/users/{user}/password', [UsersController::class, 'updatePassword'])->name('users.update-password');
        });
        Route::middleware(['permission:users.delete'])->group(function () {
            Route::delete('/users/{user}', [UsersController::class, 'destroy'])->name('users.destroy');
        });
    });

    // Roles & Permissions management
    Route::middleware(['permission:roles.view'])->group(function () {
        Route::get('/roles', [RolesController::class, 'index'])->name('roles.index');
        Route::middleware(['permission:roles.manage'])->group(function () {
            Route::put('/roles/{role}', [RolesController::class, 'update'])->name('roles.update');
        });
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/waste-management.php';
