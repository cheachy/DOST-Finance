<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SlController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect('/login'));

Route::get('/login', fn () => Inertia::render('auth/Login'))->name('login');
Route::post('/login', [AuthController::class, 'store']);

Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');

    Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger');
    Route::get('/subsidiary-ledgers', [SlController::class, 'index'])->name('subsidiary-ledgers');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');
    Route::post('/reports/rod-export', [ReportController::class, 'rodExportQueue'])->name('reports.rod-export.queue');
    Route::get('/reports/rod-export/download', [ReportController::class, 'rodExportDownload'])->name('reports.rod-export.download');
    Route::get('/logs', [ActivityLogController::class, 'index'])->name('logs');
});
