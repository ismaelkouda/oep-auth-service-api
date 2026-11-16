<?php

namespace App\Contracts;

use App\Models\User;

interface InvitationSender
{
    public function send(User $user, string $link): void;
}
