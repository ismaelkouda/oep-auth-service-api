<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RequestOtpRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

class RequestOtpController extends Controller
{
    public function __invoke(RequestOtpRequest $request, OtpService $otp): JsonResponse
    {
        $phone = (string) $request->validated('phone');

        $exists = User::query()
            ->where('type', UserType::Citizen->value)
            ->where('status', UserStatus::Active->value)
            ->where('phone', $phone)
            ->exists();

        // Réponse identique dans tous les cas (anti-énumération) ;
        // les limites d'envoi sont appliquées en silence par OtpService.
        if ($exists) {
            $otp->request($phone);
        }

        return response()->json(['message' => 'Si ce numéro est enregistré, un code vient d\'être envoyé.'], 202);
    }
}
