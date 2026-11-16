<?php

declare(strict_types=1);

use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('connecte un agent actif et renvoie un jeton opaque', function () {
    $user = User::factory()->staff()->create();

    $response = $this->postJson('/v1/auth/login', ['email' => $user->email, 'password' => 'password']);

    $response->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonMissingPath('user.password');

    $token = $response->json('token');

    expect($token)->toStartWith('oep_')->toHaveLength(68)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(AuthToken::count())->toBe(1)
        ->and(AuthToken::first()->token_hash)->not->toBe($token);
});

it('renvoie la même réponse 401 pour tous les refus', function () {
    $active = User::factory()->staff()->create();
    $suspended = User::factory()->staff()->suspended()->create();
    $locked = User::factory()->staff()->locked()->create();

    $attempts = [
        ['email' => 'inconnu@oep.test', 'password' => 'password'],
        ['email' => $active->email, 'password' => 'mauvais-mot-de-passe'],
        ['email' => $suspended->email, 'password' => 'password'],
        ['email' => $locked->email, 'password' => 'password'],
    ];

    foreach ($attempts as $attempt) {
        $this->postJson('/v1/auth/login', $attempt)
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Identifiants invalides.']);
    }

    expect(AuthToken::count())->toBe(0);
});

it('valide le format de la requête', function () {
    $this->postJson('/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('limite à 5 tentatives par minute pour un même e-mail et une même IP', function () {
    $user = User::factory()->staff()->create();

    foreach (range(1, 5) as $_) {
        $this->postJson('/v1/auth/login', ['email' => $user->email, 'password' => 'faux'])
            ->assertUnauthorized();
    }

    $this->postJson('/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertStatus(429);
});

it('stocke les mots de passe en Argon2id', function () {
    $user = User::factory()->staff()->create();

    expect(Hash::info($user->password)['algoName'])->toBe('argon2id');
});
