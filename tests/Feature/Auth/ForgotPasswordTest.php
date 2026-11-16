<?php

declare(strict_types=1);

use App\Contracts\InvitationSender;
use App\Enums\UserStatus;
use App\Models\AuthEvent;
use App\Models\User;
use Tests\Support\RecordingInvitationSender;

const RESET_PASSWORD = 'correct-horse-battery-staple';

beforeEach(function () {
    $this->invitations = new RecordingInvitationSender;
    app()->instance(InvitationSender::class, $this->invitations);
});

it('envoie un lien à un agent actif ou en attente', function () {
    $active = User::factory()->staff()->create();
    $pending = User::factory()->staff()->pending()->create();

    foreach ([$active, $pending] as $user) {
        $this->postJson('/v1/auth/password/forgot', ['email' => $user->email])->assertAccepted();
    }

    expect(collect($this->invitations->sent)->pluck('email')->sort()->values()->all())
        ->toBe(collect([$active->email, $pending->email])->sort()->values()->all());
});

it('ne rien envoie pour un e-mail inconnu, un agent suspendu ou désactivé', function () {
    $suspended = User::factory()->staff()->suspended()->create();
    $deactivated = User::factory()->staff()->create(['status' => UserStatus::Deactivated]);

    foreach (['inconnu@oep.test', $suspended->email, $deactivated->email] as $email) {
        $this->postJson('/v1/auth/password/forgot', ['email' => $email])->assertAccepted();
    }

    expect($this->invitations->sent)->toBeEmpty();
});

it('donne la même réponse dans tous les cas', function () {
    $active = User::factory()->staff()->create();

    $known = $this->postJson('/v1/auth/password/forgot', ['email' => $active->email]);
    $unknown = $this->postJson('/v1/auth/password/forgot', ['email' => 'inconnu@oep.test']);

    expect($known->status())->toBe($unknown->status())
        ->and($known->getContent())->toBe($unknown->getContent());
});

it('permet de définir un nouveau mot de passe avec le lien reçu', function () {
    $user = User::factory()->staff()->create();

    $this->postJson('/v1/auth/password/forgot', ['email' => $user->email])->assertAccepted();

    $this->postJson('/v1/auth/password/define', [
        'token' => $this->invitations->lastToken(),
        'password' => RESET_PASSWORD,
    ])->assertNoContent();

    $this->postJson('/v1/auth/login', ['email' => $user->email, 'password' => RESET_PASSWORD])->assertOk();
});

it('enregistre la demande sans conserver l\'e-mail saisi', function () {
    $user = User::factory()->staff()->create();

    $this->postJson('/v1/auth/password/forgot', ['email' => $user->email])->assertAccepted();
    $this->postJson('/v1/auth/password/forgot', ['email' => 'inconnu@oep.test'])->assertAccepted();

    expect(AuthEvent::where('event', 'password_reset_requested')->count())->toBe(2)
        ->and(AuthEvent::where('event', 'password_reset_requested')->where('user_id', $user->id)->count())->toBe(1);

    $dump = AuthEvent::all()->toJson();

    expect($dump)->not->toContain('inconnu@oep.test')
        ->and($dump)->not->toContain($user->email);
});

it('limite à 5 demandes par heure pour un même e-mail', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/v1/auth/password/forgot', ['email' => 'cible@oep.test'])->assertAccepted();
    }

    $this->postJson('/v1/auth/password/forgot', ['email' => 'cible@oep.test'])->assertStatus(429);
});

it('valide le format de l\'e-mail', function () {
    $this->postJson('/v1/auth/password/forgot', ['email' => 'pas-un-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});
