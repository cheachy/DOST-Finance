<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Transaction;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login');

Route::get('/dashboard', function () {
    return Inertia::render('Auth/Dashboard');
})->middleware('auth')->name('dashboard');

Route::post('/login', [AuthController::class, 'store']);

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::post('/imports', [ImportController::class, 'store'])
    ->middleware('auth')
    ->name('imports.store');

Route::get('/imports/{import}/preview', function (App\Models\excelImport $import) {
    $transactions = Transaction::with(['payee', 'account', 'tax'])
        ->where('import_id', $import->id)
        ->orderBy('date')
        ->orderBy('dv_month')
        ->orderBy('dv_sequence')
        ->orderBy('id')
        ->get();
        
    return Inertia::render('LedgerPreview', [
        'importId' => $import->id,
        'transactions' => $transactions
    ]);
})->middleware('auth')->name('imports.preview');
