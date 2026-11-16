<?php

declare(strict_types=1);

use App\Contracts\OtpSender;
use App\Models\AuthEvent;
use App\Models\User;
use App\Services\AuthTokenService;

beforeEach(function () {
    $this->sender = new class implements OtpSender
    {
        /** @var array<string, string> */
        public array $codes = [];

        public function send(string $phone, string $code): void
        {
            $this->codes[$phone] = $code;
        }
    };

    app()->instance(OtpSender::class, $this->sender);
});

it('enregistre une connexion réussie avec l\'utilisateur, l\'IP et le user-agent', function () {
    $user = User::factory()->staff()->create();

    $this->withHeader('User-Agent', 'TestAgent/1.0')
        ->postJson('/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk();

    $event = AuthEvent::where('event', 'login_succeeded')->sole();

    expect($event->user_id)->toBe($user->id)
        ->and($event->ip)->toBe('127.0.0.1')
        ->and($event->user_agent)->toBe('TestAgent/1.0');
});

it('enregistre un échec de connexion sans conserver l\'e-mail ni le mot de passe saisis', function () {
    $user = User::factory()->staff()->create();

    $this->postJson('/v1/auth/login', ['email' => 'inconnu@oep.test', 'password' => 'secret-saisi'])
        ->assertUnauthorized();
    $this->postJson('/v1/auth/login', ['email' => $user->email, 'password' => 'faux-mot-de-passe'])
        ->assertUnauthorized();

    expect(AuthEvent::where('event', 'login_failed')->whereNull('user_id')->count())->toBe(1)
        ->and(AuthEvent::where('event', 'login_failed')->where('user_id', $user->id)->count())->toBe(1);

    $dump = AuthEvent::all()->toJson();

    expect($dump)->not->toContain('inconnu@oep.test')
        ->and($dump)->not->toContain($user->email)
        ->and($dump)->not->toContain('secret-saisi')
        ->and($dump)->not->toContain('faux-mot-de-passe');
});

it('enregistre le parcours OTP sans conserver le téléphone ni le code', function () {
    $citizen = User::factory()->create();

    $this->postJson('/v1/auth/otp/request', ['phone' => $citizen->phone])->assertAccepted();
    $this->postJson('/v1/auth/otp/request', ['phone' => '+2250100000000'])->assertAccepted();

    $code = $this->sender->codes[$citizen->phone];

    $this->postJson('/v1/auth/otp/verify', ['phone' => $citizen->phone, 'code' => '000000'])->assertUnauthorized();
    $this->postJson('/v1/auth/otp/verify', ['phone' => $citizen->phone, 'code' => $code])->assertOk();

    expect(AuthEvent::where('event', 'otp_requested')->count())->toBe(2)
        ->and(AuthEvent::where('event', 'otp_requested')->whereNull('user_id')->count())->toBe(1)
        ->and(AuthEvent::where('event', 'otp_failed')->where('user_id', $citizen->id)->count())->toBe(1)
        ->and(AuthEvent::where('event', 'otp_verified')->where('user_id', $citizen->id)->count())->toBe(1);

    $dump = AuthEvent::all()->toJson();

    expect($dump)->not->toContain($citizen->phone)
        ->and($dump)->not->toContain($code);
});

it('enregistre la déconnexion', function () {
    $user = User::factory()->staff()->create();
    $token = app(AuthTokenService::class)->issue($user)->plainText;

    $this->withToken($token)->postJson('/v1/auth/logout')->assertNoContent();

    expect(AuthEvent::where('event', 'logout')->where('user_id', $user->id)->count())->toBe(1);
});

it('enregistre un appel d\'introspection refusé, sans utilisateur', function () {
    config()->set('service_auth.introspection_secrets', [str_repeat('a', 40)]);

    $this->withToken(str_repeat('z', 40))
        ->postJson('/v1/internal/introspect', ['token' => 'oep_x'])
        ->assertUnauthorized();

    $event = AuthEvent::where('event', 'introspection_denied')->sole();

    expect($event->user_id)->toBeNull();
});
