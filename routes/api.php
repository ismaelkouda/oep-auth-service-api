<?php

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\DefinePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\RequestOtpController;
use App\Http\Controllers\Auth\VerifyOtpController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Internal\IntrospectController;
use App\Http\Controllers\PingController;
use App\Http\Controllers\Staff\StaffAccountController;
use App\Http\Controllers\Staff\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', PingController::class);

Route::get('/health/ready', HealthController::class)
    ->middleware('throttle:health')
    ->withoutMiddleware('throttle:api');

Route::prefix('auth')->group(function () {
    Route::post('/login', LoginController::class)->middleware('throttle:login');
    Route::post('/otp/request', RequestOtpController::class)->middleware('throttle:otp');
    Route::post('/otp/verify', VerifyOtpController::class)->middleware('throttle:otp');
    Route::post('/register', RegisterController::class)->middleware('throttle:otp');
    Route::post('/password/forgot', ForgotPasswordController::class)->middleware('throttle:forgot');
    Route::post('/password/define', DefinePasswordController::class)->middleware('throttle:invitation');

    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', LogoutController::class);
        Route::get('/me', MeController::class);
        Route::post('/password', ChangePasswordController::class)->middleware('throttle:password');
    });
});

Route::middleware(['auth:api', 'accounts.manage'])->prefix('staff')->group(function () {
    Route::get('/', [StaffController::class, 'index']);
    Route::post('/', [StaffController::class, 'store']);
    Route::get('/{user}', [StaffController::class, 'show']);
    Route::patch('/{user}', [StaffController::class, 'update']);
    Route::delete('/{user}', [StaffController::class, 'destroy']);
    Route::post('/{user}/suspend', [StaffAccountController::class, 'suspend']);
    Route::post('/{user}/reactivate', [StaffAccountController::class, 'reactivate']);
    Route::post('/{user}/unlock', [StaffAccountController::class, 'unlock']);
    Route::post('/{user}/invite', [StaffAccountController::class, 'invite']);
});

Route::post('/internal/introspect', IntrospectController::class)
    ->middleware(['throttle:introspection', 'service'])
    ->withoutMiddleware('throttle:api');
