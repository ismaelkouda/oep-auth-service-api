<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Models\Profile;
use Database\Seeders\ProfileSeeder;
use Illuminate\Database\QueryException;

it('rattache des permissions à un profil', function () {
    $profile = Profile::create(['code' => 'municipal_police', 'name' => 'Police municipale']);
    $permission = Permission::create(['code' => 'report.view']);

    $profile->permissions()->attach($permission);

    expect($profile->permissions)->toHaveCount(1)
        ->and($profile->permissions->first()->code)->toBe('report.view');
});

it('crée les six profils sans doublon quand le seeder est rejoué', function () {
    $this->seed(ProfileSeeder::class);
    $this->seed(ProfileSeeder::class);

    expect(Profile::count())->toBe(6);
});

it('refuse deux profils avec le même code', function () {
    Profile::create(['code' => 'citizen', 'name' => 'Usager']);

    expect(fn () => Profile::create(['code' => 'citizen', 'name' => 'Autre']))
        ->toThrow(QueryException::class);
});
