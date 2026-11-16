<?php

declare(strict_types=1);

use App\Models\Profile;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function profileId(): string
{
    return Profile::firstOrCreate(['code' => 'citizen'], ['name' => 'Usager'])->id;
}

function insertUser(array $overrides = []): void
{
    DB::table('users')->insert(array_merge([
        'id' => (string) Str::uuid(),
        'type' => 'citizen',
        'name' => 'Usager Test',
        'phone' => '+2250102030405',
        'profile_id' => profileId(),
    ], $overrides));
}

function staffAttributes(array $overrides = []): array
{
    return array_merge([
        'type' => 'staff',
        'phone' => '+2250708091011',
        'email' => 'agent@example.test',
        'password' => 'hash-factice',
        'role' => 'manager',
        'organization_id' => (string) Str::uuid(),
    ], $overrides);
}

it('accepte un usager valide avec le statut pending par défaut', function () {
    insertUser();

    expect(DB::table('users')->value('status'))->toBe('pending');
});

it('accepte un agent valide', function () {
    insertUser(staffAttributes());

    expect(DB::table('users')->count())->toBe(1);
});

it('refuse un agent sans e-mail', function () {
    expect(fn () => insertUser(staffAttributes(['email' => null])))
        ->toThrow(QueryException::class);
});

it('refuse un agent sans rôle', function () {
    expect(fn () => insertUser(staffAttributes(['role' => null])))
        ->toThrow(QueryException::class);
});

it('refuse un usager avec un rôle', function () {
    expect(fn () => insertUser(['role' => 'manager']))
        ->toThrow(QueryException::class);
});

it('refuse un usager avec un mot de passe', function () {
    expect(fn () => insertUser(['password' => 'hash-factice']))
        ->toThrow(QueryException::class);
});

it('refuse un statut inconnu', function () {
    expect(fn () => insertUser(['status' => 'banned']))
        ->toThrow(QueryException::class);
});

it('refuse deux comptes avec le même téléphone', function () {
    insertUser();

    expect(fn () => insertUser())->toThrow(QueryException::class);
});

it('refuse de supprimer un profil encore utilisé', function () {
    insertUser();

    expect(fn () => Profile::where('code', 'citizen')->delete())
        ->toThrow(QueryException::class);
});
