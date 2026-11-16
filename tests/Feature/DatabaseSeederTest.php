<?php

declare(strict_types=1);

use App\Models\Profile;
use App\Models\User;

it('crée les six profils et aucun compte hors environnement local', function () {
    $this->seed();

    expect(Profile::count())->toBe(6)
        ->and(User::count())->toBe(0);
});

it('ne retrouve plus la route /v1/user', function () {
    $this->getJson('/v1/user')->assertNotFound();
});
