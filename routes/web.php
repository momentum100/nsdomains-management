<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\DomainController;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Routes that require authentication
Route::middleware(['auth'])->group(function () {
    Route::get('/upload', [UploadController::class, 'showUploadForm'])->name('domains.uploadForm');
    Route::post('/upload', [UploadController::class, 'uploadDomains'])->name('domains.upload');

    Route::get('/domains', [DomainController::class, 'index'])->name('domains.index');
    Route::get('/domains/export', [DomainController::class, 'exportCsv'])->name('domains.export');
    Route::delete('/domains/', [DomainController::class, 'destroy'])->name('domains.destroy');
    Route::post('/domains/mark-as-sold', [DomainController::class, 'markAsSold'])->name('domains.markAsSold');
});

// Authentication routes
Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
