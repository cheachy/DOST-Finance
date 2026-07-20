<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Http\Request;

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

Route::get('/test-upload', function () {
    return response('
        <form method="POST" action="/test-upload" enctype="multipart/form-data">
            <input type="hidden" name="_token" value="' . csrf_token() . '">
            <input type="file" name="file">
            <button type="submit">Upload</button>
        </form>
    ');
});
 
Route::post('/test-upload', function (Request $request) {
    if (!$request->hasFile('file')) {
        return response('No file received at all.');
    }
 
    $file = $request->file('file');
 
    return response(sprintf(
        'Received file: %s, size: %d bytes, valid: %s',
        $file->getClientOriginalName(),
        $file->getSize(),
        $file->isValid() ? 'yes' : 'no'
    ));
});