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
     *
     * $user : la personne qui agit ; $subject : la cible d'une action d'administration.
     */
    public function record(AuthEventType $type, Request $request, ?User $user = null, ?User $subject = null): void
    {
        $requestId = $request->attributes->get('request_id');

        $event = new AuthEvent;
        $event->forceFill([
            'event' => $type,
            'user_id' => $user?->id,
            'subject_id' => $subject?->id,
            'request_id' => is_string($requestId) ? $requestId : null,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, '') ?: null,
        ])->save();
    }
}
