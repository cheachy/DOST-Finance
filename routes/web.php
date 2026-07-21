<?php

use App\Http\Controllers\AuthController;
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

Route::post('/imports', [ImportController::class, 'store'])
    ->middleware('auth')
    ->name('imports.store');

Route::get('/imports/{import}/preview', function (Upload $import) {
    $transactions = GeneralLedger::where('upload_id', $import->id)
        ->orderBy('ledger_month')
        ->orderBy('source_row')
        ->get();

    return Inertia::render('LedgerPreview', [
        'importId' => $import->id,
        'transactions' => $transactions,
    ]);
})->middleware('auth')->name('imports.preview');

