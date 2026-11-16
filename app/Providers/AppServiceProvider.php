<?php

namespace App\Providers;

use App\Contracts\OtpSender;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\LogOtpSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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

        JsonResource::withoutWrapping();

        Auth::viaRequest('oep-token', function (Request $request): ?User {
            $plainText = $request->bearerToken();

            if ($plainText === null || $plainText === '') {
                return null;
            }

            $token = $this->app->make(AuthTokenService::class)->resolve($plainText);

            if ($token === null) {
                return null;
            }

            $request->attributes->set('auth_token', $token);

            return $token->user;
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('login|'.$email.'|'.$request->ip()),
                Limit::perHour(20)->by('login-account|'.$email),
            ];
        });

        RateLimiter::for('otp', function (Request $request) {
            return [
                Limit::perMinute(10)->by('otp-ip|'.$request->ip()),
                Limit::perMinute(5)->by('otp-phone|'.(string) $request->input('phone')),
            ];
        });
    }
}
