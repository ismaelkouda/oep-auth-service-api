<?php

declare(strict_types=1);

use App\Models\AuthEvent;
use App\Models\User;
use App\Services\ReadinessCheck;
use Illuminate\Support\Facades\Log;

function loginFrom(object $test, string $remoteAddr, array $headers = []): void
{
    $user = User::factory()->staff()->create();

    $test->withServerVariables(['REMOTE_ADDR' => $remoteAddr])
        ->withHeaders($headers)
        ->postJson('/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk();
}

it('ignore X-Forwarded-For quand aucun proxy n\'est approuvé', function () {
    config()->set('trustedproxy.proxies', null);

    loginFrom($this, '10.0.0.9', ['X-Forwarded-For' => '203.0.113.9']);

    expect(AuthEvent::where('event', 'login_succeeded')->sole()->ip)->toBe('10.0.0.9');
});

it('lit l\'adresse du client dans X-Forwarded-For pour un proxy approuvé', function () {
    config()->set('trustedproxy.proxies', '10.0.0.9');

    loginFrom($this, '10.0.0.9', ['X-Forwarded-For' => '203.0.113.9']);

    expect(AuthEvent::where('event', 'login_succeeded')->sole()->ip)->toBe('203.0.113.9');
});

it('n\'approuve pas X-Forwarded-For venant d\'une autre adresse que la passerelle', function () {
    config()->set('trustedproxy.proxies', '10.0.0.1');

    loginFrom($this, '10.0.0.9', ['X-Forwarded-For' => '203.0.113.9']);

    expect(AuthEvent::where('event', 'login_succeeded')->sole()->ip)->toBe('10.0.0.9');
});

it('reprend un X-Request-Id valide, le renvoie, le trace dans l\'audit et les journaux', function () {
    $user = User::factory()->staff()->create();

    $response = $this->withHeader('X-Request-Id', 'req-12345678')
        ->postJson('/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk();

    expect($response->headers->get('X-Request-Id'))->toBe('req-12345678')
        ->and(AuthEvent::where('event', 'login_succeeded')->sole()->request_id)->toBe('req-12345678')
        ->and(Log::sharedContext()['request_id'] ?? null)->toBe('req-12345678');
});

it('génère un identifiant quand il est absent ou invalide', function () {
    $absent = $this->getJson('/v1/ping')->headers->get('X-Request-Id');
    $invalid = $this->withHeader('X-Request-Id', 'a b')->getJson('/v1/ping')->headers->get('X-Request-Id');

    expect($absent)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($invalid)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($invalid)->not->toBe('a b');
});

it('ajoute les en-têtes de sécurité aux réponses de l\'API, erreurs comprises', function () {
    foreach ([$this->getJson('/v1/ping'), $this->getJson('/v1/auth/me')] as $response) {
        expect($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
            ->and($response->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; frame-ancestors 'none'")
            ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
    }
});

it('répond ok quand la base répond', function () {
    $this->getJson('/v1/health/ready')->assertOk()->assertExactJson(['status' => 'ok']);
});

it('répond 503 quand la base est indisponible', function () {
    app()->instance(ReadinessCheck::class, new class extends ReadinessCheck
    {
        public function isReady(): bool
        {
            return false;
        }
    });

    $this->getJson('/v1/health/ready')->assertStatus(503)->assertExactJson(['status' => 'unavailable']);
});

it('n\'est pas soumis au plafond de 30 requêtes par minute', function () {
    foreach (range(1, 31) as $_) {
        $this->getJson('/v1/health/ready')->assertOk();
    }
});
