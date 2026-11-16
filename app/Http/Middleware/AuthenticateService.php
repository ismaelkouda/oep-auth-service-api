<?php

namespace App\Http\Middleware;

use App\Enums\AuthEventType;
use App\Services\AuthEventRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateService
{
    public function __construct(private readonly AuthEventRecorder $events) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secrets = config('service_auth.introspection_secrets');

        if (! is_array($secrets) || $secrets === []) {
            Log::error('Aucun secret d\'introspection valide n\'est configuré : appel refusé.');

            return $this->deny($request);
        }

        $providedHash = hash('sha256', (string) $request->bearerToken());
        $authenticated = false;

        // On parcourt tous les secrets, sans sortie anticipée, pour garder un temps constant.
        foreach ($secrets as $secret) {
            if (is_string($secret) && hash_equals(hash('sha256', $secret), $providedHash)) {
                $authenticated = true;
            }
        }

        if (! $authenticated) {
            Log::warning('Appel d\'introspection refusé.', ['ip' => $request->ip()]);

            return $this->deny($request);
        }

        return $next($request);
    }

    private function deny(Request $request): Response
    {
        $this->events->record(AuthEventType::IntrospectionDenied, $request);

        return response()
            ->json(['message' => 'Non autorisé.'], 401)
            ->header('WWW-Authenticate', 'Bearer');
    }
}
