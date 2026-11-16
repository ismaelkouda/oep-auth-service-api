<?php

namespace App\Services;

use App\Contracts\InvitationSender;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class LogInvitationSender implements InvitationSender
{
    public function send(User $user, string $link): void
    {
        Log::info("Invitation pour {$user->email} : {$link}");
    }
}
