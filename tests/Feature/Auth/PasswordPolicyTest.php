<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FakeUncompromisedVerifier;

it('refuse un mot de passe compromis lors d\'un changement de mot de passe', function () {
    $user = User::factory()->staff()->create();
    $token = app(AuthTokenService::class)->issue($user)->plainText;

    $this->withToken($token)
        ->postJson('/v1/auth/password', [
            'current_password' => 'password',
            'password' => FakeUncompromisedVerifier::COMPROMISED,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

it('refuse un mot de passe de plus de 128 caractères', function () {
    $user = User::factory()->staff()->create();
    $token = app(AuthTokenService::class)->issue($user)->plainText;

    $this->withToken($token)
        ->postJson('/v1/auth/password', [
            'current_password' => 'password',
            'password' => str_repeat('a', 129),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});
