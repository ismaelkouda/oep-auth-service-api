<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\UserType;

it('définit les types de compte', function () {
    expect(array_column(UserType::cases(), 'value'))->toBe(['citizen', 'staff']);
});

it('définit les statuts de compte', function () {
    expect(array_column(UserStatus::cases(), 'value'))
        ->toBe(['pending', 'active', 'suspended', 'deactivated']);
});

it('hiérarchise les rôles : superviseur > chef d\'équipe > gestionnaire', function () {
    expect(UserRole::Supervisor->rank())->toBeGreaterThan(UserRole::TeamLead->rank())
        ->and(UserRole::TeamLead->rank())->toBeGreaterThan(UserRole::Manager->rank());
});
