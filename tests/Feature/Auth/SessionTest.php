<?php

declare(strict_types=1);

use App\Models\AuthToken;
use App\Models\User;
use App\Services\AuthTokenService;

it('refuse /me sans jeton ou avec un jeton invalide', function () {
    $this->getJson('/v1/auth/me')->assertUnauthorized();
    $this->withToken('oep_'.str_repeat('0', 64))->getJson('/v1/auth/me')->assertUnauthorized();
});

it('renvoie le profil de l\'utilisateur authentifié', function () {
    $user = User::factory()->staff()->create();
    $token = app(AuthTokenService::class)->issue($user)->plainText;

    $this->withToken($token)->getJson('/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonMissingPath('password');
});

it('révoque le jeton à la déconnexion', function () {
    $user = User::factory()->staff()->create();
    $token = app(AuthTokenService::class)->issue($user)->plainText;

    $this->withToken($token)->postJson('/v1/auth/logout')->assertNoContent();

    // Le garde garde l'utilisateur en mémoire pendant un test : on l'oublie entre deux requêtes.
    auth()->forgetGuards();

    $this->withToken($token)->getJson('/v1/auth/me')->assertUnauthorized();
    expect(AuthToken::whereNotNull('revoked_at')->count())->toBe(1);
});

it('refuse le jeton d\'un utilisateur suspendu', function () {
    $user = User::factory()->staff()->suspended()->create();
    $token = app(AuthTokenService::class)->issue($user)->plainText;

    $this->withToken($token)->getJson('/v1/auth/me')->assertUnauthorized();
});
