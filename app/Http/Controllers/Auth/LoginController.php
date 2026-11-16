<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, AuthTokenService $tokens): JsonResponse
    {
        $password = (string) $request->validated('password');

        $user = User::query()
            ->where('type', UserType::Staff->value)
            ->where('email', (string) $request->validated('email'))
            ->first();

        // Temps constant : on vérifie toujours un hash, même si le compte n'existe pas.
        $matches = Hash::check($password, $user->password ?? $this->dummyHash());

        if ($user === null || ! $matches || ! $user->isActive() || $user->isLocked()) {
            return response()->json(['message' => 'Identifiants invalides.'], 401);
        }

        if (Hash::needsRehash($user->password)) {
            $user->password = $password;
            $user->save();
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

    private function dummyHash(): string
    {
        return Cache::rememberForever(
            'auth.dummy-password-hash',
            fn (): string => Hash::make(Str::random(40)),
        );
    }
}
