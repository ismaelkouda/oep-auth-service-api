<?php

declare(strict_types=1);

use App\Models\AuthToken;
use App\Models\User;
use App\Services\AuthTokenService;

function tokens(): AuthTokenService
{
    return app(AuthTokenService::class);
}

it('émet un jeton préfixé et ne stocke que son empreinte', function () {
    $issued = tokens()->issue(User::factory()->staff()->create());

    expect($issued->plainText)->toStartWith('oep_')->toHaveLength(68)
        ->and($issued->token->token_hash)->toBe(hash('sha256', $issued->plainText))
        ->and(AuthToken::where('token_hash', $issued->plainText)->exists())->toBeFalse();
});

it('fixe la durée de vie selon le type de compte', function () {
    $this->travelTo(now()->startOfSecond());

    $staff = tokens()->issue(User::factory()->staff()->create());
    $citizen = tokens()->issue(User::factory()->create());

    expect($staff->token->expires_at->equalTo(now()->addHours(8)))->toBeTrue()
        ->and($citizen->token->expires_at->equalTo(now()->addDays(30)))->toBeTrue();
});

it('retrouve un jeton valide et note son dernier usage', function () {
    $this->travelTo(now()->startOfSecond());
    $user = User::factory()->staff()->create();
    $issued = tokens()->issue($user);

    $resolved = tokens()->resolve($issued->plainText);

    expect($resolved)->not->toBeNull()
        ->and($resolved->user->is($user))->toBeTrue()
        ->and($resolved->last_used_at->equalTo(now()))->toBeTrue();
});

it('ne réécrit pas le dernier usage dans la même minute', function () {
    $this->travelTo(now()->startOfSecond());
    $issued = tokens()->issue(User::factory()->staff()->create());

    tokens()->resolve($issued->plainText);
    $first = AuthToken::firstOrFail()->last_used_at;

    $this->travel(30)->seconds();
    tokens()->resolve($issued->plainText);

    expect(AuthToken::firstOrFail()->last_used_at->equalTo($first))->toBeTrue();
});

it('refuse un jeton inconnu ou mal préfixé', function () {
    expect(tokens()->resolve('oep_'.str_repeat('a', 64)))->toBeNull()
        ->and(tokens()->resolve('jeton-quelconque'))->toBeNull();
});

it('refuse un jeton expiré', function () {
    $issued = tokens()->issue(User::factory()->staff()->create());

    $this->travel(9)->hours();

    expect(tokens()->resolve($issued->plainText))->toBeNull();
});

it('refuse un jeton révoqué', function () {
    $issued = tokens()->issue(User::factory()->staff()->create());

    tokens()->revoke($issued->token);

    expect(tokens()->resolve($issued->plainText))->toBeNull();
});

it('refuse le jeton d\'un compte suspendu', function () {
    $issued = tokens()->issue(User::factory()->staff()->suspended()->create());

    expect(tokens()->resolve($issued->plainText))->toBeNull();
});

it('refuse le jeton d\'un compte supprimé', function () {
    $user = User::factory()->staff()->create();
    $issued = tokens()->issue($user);

    $user->delete();

    expect(tokens()->resolve($issued->plainText))->toBeNull();
});

it('révoque tous les jetons d\'un compte sans toucher aux autres', function () {
    $user = User::factory()->staff()->create();
    $other = User::factory()->staff()->create();
    $first = tokens()->issue($user);
    $second = tokens()->issue($user);
    $otherToken = tokens()->issue($other);

    $count = tokens()->revokeAllFor($user);

    expect($count)->toBe(2)
        ->and(tokens()->resolve($first->plainText))->toBeNull()
        ->and(tokens()->resolve($second->plainText))->toBeNull()
        ->and(tokens()->resolve($otherToken->plainText))->not->toBeNull();
});
