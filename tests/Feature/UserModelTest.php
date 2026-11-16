<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Support\Facades\Hash;

it('convertit type, rôle et statut en énumérations', function () {
    $id = User::factory()->staff(UserRole::Supervisor)->create()->id;

    $user = User::find($id);

    expect($user->type)->toBe(UserType::Staff)
        ->and($user->role)->toBe(UserRole::Supervisor)
        ->and($user->status)->toBe(UserStatus::Active);
});

it('crée un usager sans rôle ni mot de passe', function () {
    $user = User::factory()->create();

    expect($user->type)->toBe(UserType::Citizen)
        ->and($user->role)->toBeNull()
        ->and($user->password)->toBeNull();
});

it('hache le mot de passe de l\'agent et le masque dans la sérialisation', function () {
    $user = User::factory()->staff()->create();

    expect(Hash::check('password', $user->password))->toBeTrue()
        ->and($user->toArray())->not->toHaveKey('password');
});

it('relie un utilisateur à son profil et inversement', function () {
    $profile = Profile::factory()->create();
    $user = User::factory()->for($profile)->create();

    expect(User::with('profile')->find($user->id)->profile->is($profile))->toBeTrue()
        ->and(Profile::with('users')->find($profile->id)->users)->toHaveCount(1);
});

it('refuse l\'affectation de masse des champs de privilège', function () {
    expect(fn () => new User(['name' => 'Test', 'role' => 'supervisor']))
        ->toThrow(MassAssignmentException::class);
});

it('supprime logiquement un utilisateur', function () {
    $user = User::factory()->create();

    $user->delete();

    expect(User::count())->toBe(0)
        ->and(User::withTrashed()->count())->toBe(1);
});

it('sait si un compte est du personnel, actif ou verrouillé', function () {
    expect(User::factory()->staff()->create()->isStaff())->toBeTrue()
        ->and(User::factory()->create()->isStaff())->toBeFalse()
        ->and(User::factory()->suspended()->create()->isActive())->toBeFalse()
        ->and(User::factory()->locked()->create()->isLocked())->toBeTrue()
        ->and(User::factory()->create()->isLocked())->toBeFalse();
});
