<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuthEventType;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthEventRecorder;
use App\Services\AuthTokenService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

class VerifyOtpController extends Controller
{
    public function __invoke(
        VerifyOtpRequest $request,
        OtpService $otp,
        AuthTokenService $tokens,
        AuthEventRecorder $events,
    ): JsonResponse {
        $phone = (string) $request->validated('phone');

        $user = User::query()
            ->where('type', UserType::Citizen->value)
            ->where('phone', $phone)
            ->first();

        // La vérification est toujours exécutée, même pour un numéro inconnu.
        $valid = $otp->verify($phone, (string) $request->validated('code'));

        if (! $valid || $user === null || ! $user->isActive() || $user->isLocked()) {
            $events->record(AuthEventType::OtpFailed, $request, $user);

            return response()->json(['message' => 'Code invalide ou expiré.'], 401);
        }

        $issued = $tokens->issue($user);

        $events->record(AuthEventType::OtpVerified, $request, $user);

        return response()
            ->json([
                'token' => $issued->plainText,
                'token_type' => 'Bearer',
                'expires_at' => $issued->token->expires_at->toIso8601String(),
                'user' => (new UserResource($user))->resolve(),
            ])
            ->header('Cache-Control', 'no-store');
    }
}
