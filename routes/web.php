<?php

use App\Http\Controllers\CsvController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('reports');
    }
    return view('auth.login');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/reports', function () {
        return view('reports');
    })->name('reports');

    // upload csv
    Route::post('/upload-csv', [CsvController::class, 'uploadCsv'])->name('upload.csv');

    // register
    Route::get('/register', function () {
        return view('auth.register');
    })->name('register');

    Route::post('/create-user', [UserController::class, 'register'])->name('create.user');

    // download custom excel
    Route::post('/download-excel', [CsvController::class, 'downloadExcel'])->name('download.excel');
});
