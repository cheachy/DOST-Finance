<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\GeneralLedger;
use App\Models\Upload;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/login', function () {
    return Inertia::render('auth/Login');
})->name('login');

Route::get('/dashboard', function () {
    return Inertia::render('auth/Dashboard');
})->middleware('auth')->name('dashboard');

Route::post('/login', [AuthController::class, 'store']);

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
 
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
 
    // stubs
    Route::get('/ledger', fn () => inertia('ledger/Index'))->name('ledger');
    Route::get('/subsidiary-ledgers', fn () => inertia('subsidiaryledgers/Index'))->name('subsidiary-ledgers');
    Route::get('/reports', fn () => inertia('reports/Index'))->name('reports');
    Route::get('/logs', fn () => inertia('activitylogs/Index'))->name('logs');
});