<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

class VerifyOtpController extends Controller
{
    public function __invoke(VerifyOtpRequest $request, OtpService $otp, AuthTokenService $tokens): JsonResponse
    {
        $phone = (string) $request->validated('phone');

        $valid = $otp->verify($phone, (string) $request->validated('code'));

        $user = $valid
            ? User::query()
                ->where('type', UserType::Citizen->value)
                ->where('phone', $phone)
                ->first()
            : null;

        if ($user === null || ! $user->isActive() || $user->isLocked()) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 401);
        }

        $issued = $tokens->issue($user);

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
