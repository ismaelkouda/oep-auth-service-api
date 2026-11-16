<?php

declare(strict_types=1);

use App\Models\AuthEvent;
use App\Models\AuthToken;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Support\Facades\Hash;

const NEW_PASSWORD = 'correct-horse-battery-staple';

it('change le mot de passe, révoque tous les jetons et permet la reconnexion', function () {
    $user = User::factory()->staff()->create();
    $service = app(AuthTokenService::class);
    $current = $service->issue($user)->plainText;
    $service->issue($user);

    $this->withToken($current)
        ->postJson('/v1/auth/password', ['current_password' => 'password', 'password' => NEW_PASSWORD])
        ->assertNoContent();

    auth()->forgetGuards();

    $this->withToken($current)->getJson('/v1/auth/me')->assertUnauthorized();

    expect(AuthToken::whereNull('revoked_at')->count())->toBe(0)
        ->and(Hash::info($user->refresh()->password)['algoName'])->toBe('argon2id')
        ->and(AuthEvent::where('event', 'password_changed')->where('user_id', $user->id)->count())->toBe(1);

    $this->postJson('/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnauthorized();
    $this->postJson('/v1/auth/login', ['email' => $user->email, 'password' => NEW_PASSWORD])->assertOk();
});

it('refuse un mauvais mot de passe actuel sans rien modifier', function () {
    $user = User::factory()->staff()->create();
    $token = app(AuthTokenService::class)->issue($user)->plainText;

    $this->withToken($token)
        ->postJson('/v1/auth/password', ['current_password' => 'faux', 'password' => NEW_PASSWORD])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);

    auth()->forgetGuards();

    $this->withToken($token)->getJson('/v1/auth/me')->assertOk();

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue()
        ->and(AuthEvent::where('event', 'password_change_failed')->where('user_id', $user->id)->count())->toBe(1);
});

it('refuse un mot de passe trop court ou identique à l\'actuel', function () {
    $user = User::factory()->staff()->create();
    $token = app(AuthTokenService::class)->issue($user)->plainText;

    $this->withToken($token)
        ->postJson('/v1/auth/password', ['current_password' => 'password', 'password' => 'abcdefghijklmn'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    $this->withToken($token)
        ->postJson('/v1/auth/password', ['current_password' => 'password', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('refuse un usager sans mot de passe et un appel sans jeton', function () {
    $citizen = User::factory()->create();
    $token = app(AuthTokenService::class)->issue($citizen)->plainText;

    $this->withToken($token)
        ->postJson('/v1/auth/password', ['current_password' => 'x', 'password' => NEW_PASSWORD])
        ->assertForbidden();

    auth()->forgetGuards();

    $this->withoutToken()
        ->postJson('/v1/auth/password', ['current_password' => 'x', 'password' => NEW_PASSWORD])
        ->assertUnauthorized();
});

it('limite à 5 tentatives par minute', function () {
    $user = User::factory()->staff()->create();
    $token = app(AuthTokenService::class)->issue($user)->plainText;

    foreach (range(1, 5) as $_) {
        $this->withToken($token)
            ->postJson('/v1/auth/password', ['current_password' => 'faux', 'password' => NEW_PASSWORD])
            ->assertUnprocessable();
    }

    $this->withToken($token)
        ->postJson('/v1/auth/password', ['current_password' => 'faux', 'password' => NEW_PASSWORD])
        ->assertStatus(429);
});
