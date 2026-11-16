<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuthEventType;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Models\User;
use App\Services\AuthEventRecorder;
use App\Services\OtpGate;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

class RequestOtpController extends Controller
{
    public function __invoke(
        RequestOtpRequest $request,
        OtpService $otp,
        OtpGate $gate,
        AuthEventRecorder $events,
    ): JsonResponse {
        $phone = (string) $request->validated('phone');

        $user = User::query()->where('phone', $phone)->first();

        // Un SMS part pour un numéro inconnu (inscription) ou pour un citoyen actif (connexion).
        // Jamais pour un agent, ni pour un compte suspendu ou désactivé.
        $eligible = $user === null
            || ($user->type === UserType::Citizen && $user->isActive());

        // Réponse identique dans tous les cas (anti-énumération) ;
        // les limites d'envoi sont appliquées en silence.
        if ($eligible && $gate->allows($phone)) {
            $otp->request($phone);
        }

        $events->record(AuthEventType::OtpRequested, $request, $user);

        return response()->json(['message' => 'Si ce numéro est autorisé, un code vient d\'être envoyé.'], 202);
    }
}
