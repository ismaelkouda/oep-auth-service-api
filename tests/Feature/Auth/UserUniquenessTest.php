<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

it('libère le téléphone d\'un compte supprimé', function () {
    $old = User::factory()->create(['phone' => '+2250700000001']);
    $old->delete();

    $new = User::factory()->create(['phone' => '+2250700000001']);

    expect($new->exists)->toBeTrue();
});

it('libère l\'e-mail d\'un agent supprimé', function () {
    $old = User::factory()->staff()->create(['email' => 'agent@oep.test']);
    $old->delete();

    $new = User::factory()->staff()->create(['email' => 'agent@oep.test']);

    expect($new->exists)->toBeTrue();
});

it('garde l\'unicité du téléphone entre comptes non supprimés', function () {
    User::factory()->create(['phone' => '+2250700000002']);

    expect(fn () => User::factory()->create(['phone' => '+2250700000002']))
        ->toThrow(UniqueConstraintViolationException::class);
});
