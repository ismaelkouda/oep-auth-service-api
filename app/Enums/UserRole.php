<?php

namespace App\Enums;

enum UserRole: string
{
    case Supervisor = 'supervisor';
    case TeamLead = 'team_lead';
    case Manager = 'manager';

    public function rank(): int
    {
        return match ($this) {
            self::Supervisor => 3,
            self::TeamLead => 2,
            self::Manager => 1,
        };
    }
}
