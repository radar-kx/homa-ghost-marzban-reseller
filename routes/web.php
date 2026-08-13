<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\DepositReviewController;
use App\Http\Controllers\Admin\PanelController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ResellerController;
use App\Http\Controllers\Admin\OperationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\ServiceController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::middleware('reseller')->group(function () {
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::get('/services/{publicId}', [ServiceController::class, 'show'])->name('services.show');
        Route::post('/services/{publicId}/renew', [ServiceController::class, 'renew'])->name('services.renew');
        Route::get('/wallet', [DepositController::class, 'index'])->name('wallet.index');
        Route::get('/wallet/charge', [DepositController::class, 'create'])->name('wallet.create');
        Route::post('/wallet/charge', [DepositController::class, 'store'])->name('wallet.store');
    });

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
        Route::resource('resellers', ResellerController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::post('/resellers/{reseller}/toggle', [ResellerController::class, 'toggle'])->name('resellers.toggle');
        Route::resource('panels', PanelController::class)->except(['show', 'destroy']);
        Route::post('/panels/{panel}/test', [PanelController::class, 'test'])->name('panels.test');
        Route::resource('plans', PlanController::class)->except(['show', 'destroy']);
        Route::get('/deposits', [DepositReviewController::class, 'index'])->name('deposits.index');
        Route::get('/deposits/{deposit}/receipt', [DepositReviewController::class, 'receipt'])->name('deposits.receipt');
        Route::post('/deposits/{deposit}/approve', [DepositReviewController::class, 'approve'])->name('deposits.approve');
        Route::post('/deposits/{deposit}/reject', [DepositReviewController::class, 'reject'])->name('deposits.reject');
        Route::get('/operations', [OperationController::class, 'index'])->name('operations.index');
        Route::post('/operations/{operation}/reconcile', [OperationController::class, 'reconcile'])->name('operations.reconcile');
    });
});
