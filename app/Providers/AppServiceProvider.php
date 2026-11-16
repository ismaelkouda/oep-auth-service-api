<?php

namespace App\Providers;

use App\Contracts\OtpSender;
use App\Services\LogOtpSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OtpSender::class, fn (): OtpSender => match (config('otp.driver')) {
            'log' => $this->app->isProduction()
                ? throw new RuntimeException('Le pilote OTP « log » est interdit en production.')
                : new LogOtpSender,
            default => throw new RuntimeException('Aucun fournisseur de SMS configuré pour les OTP.'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}
