<?php

namespace App\Providers;

use App\Contracts\InvitationSender;
use App\Contracts\OtpSender;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\LogInvitationSender;
use App\Services\LogOtpSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;
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

        $this->app->bind(InvitationSender::class, fn (): InvitationSender => match (config('staff.invitation.driver')) {
            'log' => $this->app->isProduction()
                ? throw new RuntimeException('Le pilote d\'invitation « log » est interdit en production.')
                : new LogInvitationSender,
            default => throw new RuntimeException('Aucun fournisseur d\'e-mail configuré pour les invitations.'),
        });

        // Vérification des mots de passe compromis (HIBP, 5 premiers caractères de l'empreinte) :
        // délai de 3 s au lieu de 30 s par défaut.
        $this->app->extend(
            UncompromisedVerifier::class,
            fn (UncompromisedVerifier $verifier, Application $app): UncompromisedVerifier => new NotPwnedVerifier($app->make(HttpFactory::class), 3),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        JsonResource::withoutWrapping();

        Password::defaults(fn (): Password => Password::min(15)->max(128)->uncompromised());

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

        RateLimiter::for('introspection', function (Request $request) {
            return Limit::perMinute(6000)->by('introspection|'.$request->ip());
        });

        RateLimiter::for('password', function (Request $request) {
            return Limit::perMinute(5)->by('password|'.($request->user('api')?->id ?: $request->ip()));
        });

        RateLimiter::for('invitation', function (Request $request) {
            return Limit::perMinute(10)->by('invitation|'.$request->ip());
        });

        RateLimiter::for('forgot', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perHour(5)->by('forgot-email|'.$email),
                Limit::perMinute(10)->by('forgot-ip|'.$request->ip()),
            ];
        });

        RateLimiter::for('health', function (Request $request) {
            return Limit::perMinute(120)->by('health|'.$request->ip());
        });
    }
}
