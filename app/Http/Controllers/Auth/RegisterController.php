<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuthEventType;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Profile;
use App\Models\User;
use App\Services\AuthEventRecorder;
use App\Services\AuthTokenService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(
        RegisterRequest $request,
        OtpService $otp,
        AuthTokenService $tokens,
        AuthEventRecorder $events,
    ): JsonResponse {
        $phone = (string) $request->validated('phone');

        $existing = User::query()->where('phone', $phone)->first();

        // La vérification est toujours exécutée, même pour un numéro inconnu ou réservé à un agent.
        $valid = $otp->verify($phone, (string) $request->validated('code'));

        if (! $valid) {
            $events->record(AuthEventType::OtpFailed, $request, $existing);

            return response()->json(['message' => 'Code invalide ou expiré.'], 401);
        }

        if ($existing !== null) {
            // Le propriétaire du numéro a prouvé qu'il le possède : « s'inscrire ou se connecter ».
            if ($existing->type !== UserType::Citizen || ! $existing->isActive() || $existing->isLocked()) {
                $events->record(AuthEventType::OtpFailed, $request, $existing);

                return response()->json(['message' => 'Code invalide ou expiré.'], 401);
            }

            $events->record(AuthEventType::OtpVerified, $request, $existing);

            return $this->tokenResponse($tokens, $existing, 200);
        }

        $user = new User;
        $user->forceFill([
            'type' => UserType::Citizen,
            'name' => (string) $request->validated('name'),
            'phone' => $phone,
            'phone_verified_at' => now(),
            'profile_id' => Profile::query()->where('code', 'citizen')->firstOrFail()->id,
            'status' => UserStatus::Active,
        ])->save();

        $events->record(AuthEventType::Registered, $request, $user);

        return $this->tokenResponse($tokens, $user, 201);
    }

    private function tokenResponse(AuthTokenService $tokens, User $user, int $status): JsonResponse
    {
        $issued = $tokens->issue($user);

        return response()
            ->json([
                'token' => $issued->plainText,
                'token_type' => 'Bearer',
                'expires_at' => $issued->token->expires_at->toIso8601String(),
                'user' => (new UserResource($user))->resolve(),
            ], $status)
            ->header('Cache-Control', 'no-store');
    }
}
