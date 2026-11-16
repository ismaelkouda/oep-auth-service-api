<?php

namespace App\Services;

use App\Enums\AuthEventType;
use App\Models\AuthEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AuthEventRecorder
{
    /**
     * Enregistre un événement. Jamais de mot de passe, de jeton, de code OTP,
     * ni d'identifiant saisi par un inconnu (e-mail, téléphone).
     */
    public function record(AuthEventType $type, Request $request, ?User $user = null): void
    {
        $event = new AuthEvent;
        $event->forceFill([
            'event' => $type,
            'user_id' => $user?->id,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, '') ?: null,
        ])->save();
    }
}
