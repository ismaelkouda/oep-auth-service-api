<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RequestOtpController;
use App\Http\Controllers\Auth\VerifyOtpController;
use App\Http\Controllers\PingController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', PingController::class);

Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class)->middleware('throttle:login');
    Route::post('/otp/request', RequestOtpController::class)->middleware('throttle:otp');
    Route::post('/otp/verify', VerifyOtpController::class)->middleware('throttle:otp');

    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', LogoutController::class);
        Route::get('/me', MeController::class);
    });
});
