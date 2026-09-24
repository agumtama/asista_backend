<?php

use App\Http\Controllers\AdminController;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::view('/payments/finish', 'payment-finish')->name('payment.finish');
Route::view('/tentang-asista', 'about-asista')->name('brand.about');
Route::view('/login', 'admin.login')->name('login');
Route::post('/login', [AdminController::class, 'login'])->middleware('throttle:5,1');
Route::middleware(AdminOnly::class)->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
    Route::post('/logout', [AdminController::class, 'logout']);
    Route::post('/admin/verify/{table}/{id}', [AdminController::class, 'verify']);
    Route::get('/admin/document/{id}', [AdminController::class, 'document'])->name('admin.document');
    Route::get('/admin/registrants/{id}', [AdminController::class, 'registrant'])->name('admin.registrant');
    Route::post('/admin/bookings/{id}/payment', [AdminController::class, 'payment']);
    Route::post('/admin/reports/{id}', [AdminController::class, 'safety']);
});
