<?php

declare(strict_types=1);

use App\Contracts\OtpSender;
use App\Enums\UserStatus;
use App\Models\User;

beforeEach(function () {
    $this->sender = new class implements OtpSender
    {
        /** @var array<string, string> */
        public array $codes = [];

        public int $calls = 0;

        public function send(string $phone, string $code): void
        {
            $this->codes[$phone] = $code;
            $this->calls++;
        }
    };

    app()->instance(OtpSender::class, $this->sender);
});

it('envoie un code à un citoyen actif et répond 202', function () {
    $citizen = User::factory()->create();

    $this->postJson('/v1/auth/otp/request', ['phone' => $citizen->phone])->assertAccepted();

    expect($this->sender->codes[$citizen->phone] ?? null)->toMatch('/^\d{6}$/');
});

it('envoie un code à un numéro inconnu autorisé (inscription)', function () {
    $this->postJson('/v1/auth/otp/request', ['phone' => '+2250100000000'])->assertAccepted();

    expect($this->sender->codes['+2250100000000'] ?? null)->toMatch('/^\d{6}$/');
});

it('répond 202 sans rien envoyer pour un agent, un compte inactif ou un préfixe non autorisé', function () {
    $staff = User::factory()->staff()->create();
    $pending = User::factory()->pending()->create();
    $suspended = User::factory()->suspended()->create();
    $deactivated = User::factory()->create(['status' => UserStatus::Deactivated]);

    $phones = [$staff->phone, $pending->phone, $suspended->phone, $deactivated->phone, '+33612345678'];

    foreach ($phones as $phone) {
        $this->postJson('/v1/auth/otp/request', ['phone' => $phone])->assertAccepted();
    }

    expect($this->sender->calls)->toBe(0);
});

it('répond 202 aussi quand la limite d\'envoi est atteinte, sans renvoyer de code', function () {
    $citizen = User::factory()->create();

    $this->postJson('/v1/auth/otp/request', ['phone' => $citizen->phone])->assertAccepted();
    $this->postJson('/v1/auth/otp/request', ['phone' => $citizen->phone])->assertAccepted();

    expect($this->sender->calls)->toBe(1);
});

it('refuse un numéro mal formé', function () {
    $this->postJson('/v1/auth/otp/request', ['phone' => '0700000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('connecte le citoyen avec le bon code, une seule fois', function () {
    $citizen = User::factory()->create();
    $this->postJson('/v1/auth/otp/request', ['phone' => $citizen->phone])->assertAccepted();
    $code = $this->sender->codes[$citizen->phone];

    $response = $this->postJson('/v1/auth/otp/verify', ['phone' => $citizen->phone, 'code' => $code]);

    $response->assertOk()->assertJsonPath('user.id', $citizen->id);
    expect($response->json('token'))->toStartWith('oep_');

    $this->postJson('/v1/auth/otp/verify', ['phone' => $citizen->phone, 'code' => $code])
        ->assertUnauthorized();
});

it('refuse un mauvais code et un numéro inconnu avec la même réponse', function () {
    $citizen = User::factory()->create();
    $this->postJson('/v1/auth/otp/request', ['phone' => $citizen->phone])->assertAccepted();

    $this->postJson('/v1/auth/otp/verify', ['phone' => $citizen->phone, 'code' => '000000'])
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Code invalide ou expiré.']);

    $this->postJson('/v1/auth/otp/verify', ['phone' => '+2250100000000', 'code' => '000000'])
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Code invalide ou expiré.']);
});
