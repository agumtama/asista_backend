<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AgencyPortalController;
use App\Http\Controllers\AgencyRegistrationController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\AgencyOnly;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::view('/payments/finish', 'payment-finish')->name('payment.finish');
Route::view('/tentang-asista', 'about-asista')->name('brand.about');
Route::view('/login', 'admin.login')->name('login');
Route::view('/reset-password/success', 'password-reset-success')->name('password.reset.success');
Route::get('/reset-password/{token}', fn (string $token) => response()->view('password-reset', ['token' => $token])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer'))->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
Route::get('/register/agency', [AgencyRegistrationController::class, 'create'])->name('agency.register');
Route::post('/register/agency', [AgencyRegistrationController::class, 'store'])->middleware('throttle:5,10')->name('agency.register.store');
Route::get('/register/agency/verify', [AgencyRegistrationController::class, 'verification'])->name('agency.verify');
Route::post('/register/agency/verify', [AgencyRegistrationController::class, 'verify'])->middleware('throttle:5,1');
Route::post('/register/agency/resend', [AgencyRegistrationController::class, 'sendCode'])->middleware('throttle:3,10')->name('agency.resend');
Route::post('/login', [AdminController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AdminController::class, 'logout'])->middleware('auth');
Route::middleware(AgencyOnly::class)->group(function () {
    Route::get('/agency/dashboard', [AgencyPortalController::class, 'dashboard'])->name('agency.dashboard');
    Route::get('/agency', [AgencyPortalController::class, 'index'])->name('agency.portal');
    Route::post('/agency/profile', [AgencyPortalController::class, 'profile'])->name('agency.profile');
    Route::post('/agency/manager', [AgencyPortalController::class, 'manager'])->name('agency.manager');
    Route::post('/agency/documents', [AgencyPortalController::class, 'uploadDocument'])->name('agency.documents.upload');
    Route::get('/agency/documents/{id}', [AgencyPortalController::class, 'document'])->name('agency.document');
    Route::post('/agency/rates', [AgencyPortalController::class, 'rate'])->name('agency.rates');
    Route::post('/agency/rates/{id}/apply', [AgencyPortalController::class, 'applyRate'])->name('agency.rates.apply');
    Route::get('/agency/rates/{id}/preview', [AgencyPortalController::class, 'previewRate'])->name('agency.rates.preview');
    Route::get('/agency/workers/{id}', [AgencyPortalController::class, 'worker'])->name('agency.worker');
    Route::get('/agency/workers/{id}/photo', [AgencyPortalController::class, 'photo'])->name('agency.worker.photo');
    Route::post('/agency/workers/{id}/video', [AgencyPortalController::class, 'uploadVideo'])->name('agency.video.upload');
    Route::get('/agency/workers/{id}/video', [AgencyPortalController::class, 'video'])->name('agency.video');
});
Route::middleware(AdminOnly::class)->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
    Route::post('/admin/verify/{table}/{id}', [AdminController::class, 'verify']);
    Route::get('/admin/document/{id}', [AdminController::class, 'document'])->name('admin.document');
    Route::get('/admin/registrants/{id}', [AdminController::class, 'registrant'])->name('admin.registrant');
    Route::post('/admin/bookings/{id}/payment', [AdminController::class, 'payment']);
    Route::post('/admin/reports/{id}', [AdminController::class, 'safety']);
});
