<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\DomainController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\GetQuoteController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminCryptoPaymentController;

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
    if (auth()->check()) {
        if (auth()->user()->is_admin) {
            return redirect('/domains');
        }
        return redirect('/home');
    }
    return redirect('/getquote');
});

// Admin routes
Route::middleware(['auth', 'admin'])->group(function () {
    // Upload functionality - admin only
    Route::get('/upload', [UploadController::class, 'showUploadForm'])->name('domains.uploadForm');
    Route::post('/upload', [UploadController::class, 'uploadDomains'])->name('domains.upload');

    // Domain management - admin only
    Route::delete('/domains/', [DomainController::class, 'destroy'])->name('domains.destroy');
    Route::post('/domains/mark-as-sold', [DomainController::class, 'markAsSold'])->name('domains.markAsSold');
    Route::get('/domains', [DomainController::class, 'index'])->name('domains.index');
    Route::post('/domains/filter', [DomainController::class, 'index'])->name('domains.filter');
    Route::get('/domains/export', [DomainController::class, 'exportCsv'])->name('domains.export');

    // Add new route for registrar filtering
    Route::get('/domains/registrar/{registrar}', [DomainController::class, 'indexByRegistrar'])
        ->name('domains.byRegistrar');

    // Admin: Crypto payment check tool
    Route::get('/admin/crypto-payment-check', [AdminCryptoPaymentController::class, 'showForm'])->name('admin.crypto-payment-check');
    Route::post('/admin/crypto-payment-check', [AdminCryptoPaymentController::class, 'checkTransaction'])->name('admin.crypto-payment-check.post');
});

// Authenticated user routes (non-admin)
Route::middleware(['auth'])->group(function () {
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
    
    // Add these new routes for payment details
    Route::get('/dashboard', [UserController::class, 'dashboard'])->name('dashboard');
    Route::post('/user/payment', [UserController::class, 'updatePaymentDetails'])->name('user.payment.update');
});

// Public routes
Route::get('/public', [DomainController::class, 'publicList'])->name('domains.public');
Route::get('/getquote/{uuid?}', [GetQuoteController::class, 'showForm'])->name('getquote.form');
Route::post('/getquote', [GetQuoteController::class, 'getQuote'])->name('getquote.process');

// Authentication routes
Auth::routes(['register' => true]);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
