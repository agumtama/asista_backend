<?php

use App\Http\Controllers\ApiController as Api;
use App\Http\Controllers\JourneyController as Journey;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\ApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:120,1')->group(function () {
    Route::post('payments/midtrans/notification', [PaymentController::class, 'notification']);
    Route::post('register', [Api::class, 'register'])->middleware('throttle:5,1,register');
    Route::post('login', [Api::class, 'login'])->middleware('throttle:5,1,login');
    Route::get('workers', [Api::class, 'workers']);
    Route::get('workers/{id}', [Api::class, 'worker']);
    Route::middleware(ApiToken::class)->group(function () {
        Route::post('bookings/{id}/payment', [PaymentController::class, 'checkout'])->middleware('throttle:10,1,payment-checkout');
        Route::post('bookings/{id}/payment/refresh', [PaymentController::class, 'refresh'])->middleware('throttle:20,1,payment-refresh');
        Route::get('me', fn (Request $r) => $r->user());
        Route::get('my-profile', [Journey::class, 'ownProfile']);
        Route::get('shortlist', [Journey::class, 'shortlist']);
        Route::put('shortlist/{id}', [Journey::class, 'saveWorker']);
        Route::delete('shortlist/{id}', [Journey::class, 'removeWorker']);
        Route::get('needs', [Journey::class, 'needs']);
        Route::post('needs', [Journey::class, 'need']);
        Route::get('needs/{id}/matches', [Journey::class, 'matches']);
        Route::get('bookings/{id}/interviews', [Journey::class, 'interviews']);
        Route::post('bookings/{id}/interviews', [Journey::class, 'interview']);
        Route::patch('interviews/{id}', [Journey::class, 'interviewStatus']);
        Route::get('agency-workspace', [Journey::class, 'agencyWorkspace']);
        Route::post('agency-invitations', [Journey::class, 'invite']);
        Route::post('agency-transfer', [Journey::class, 'transfer']);
        Route::patch('agency-relationships/{id}', [Journey::class, 'relationship']);
        Route::patch('agency-workers/{id}/fee', [Journey::class, 'agencyFee']);
        Route::post('logout', [Api::class, 'logout']);
        Route::put('worker-profile', [Api::class, 'profile']);
        Route::put('agency-profile', [Api::class, 'agency']);
        Route::post('verification', [Api::class, 'verification']);
        Route::get('bookings', [Api::class, 'bookings']);
        Route::post('bookings', [Api::class, 'book']);
        Route::get('bookings/{id}', [Api::class, 'booking']);
        Route::patch('bookings/{id}/status', [Api::class, 'transition']);
        Route::get('bookings/{id}/messages', [Api::class, 'messages']);
        Route::post('bookings/{id}/messages', [Api::class, 'message']);
        Route::post('bookings/{id}/reviews', [Api::class, 'review']);
        Route::post('bookings/{id}/reports', [Api::class, 'report']);
        Route::get('reports', [Api::class, 'reports']);
        Route::post('reports/{id}/appeal', [Api::class, 'appeal']);
    });
});
