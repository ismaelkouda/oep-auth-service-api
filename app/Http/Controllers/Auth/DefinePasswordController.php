<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuthEventType;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DefinePasswordRequest;
use App\Services\AuthEventRecorder;
use App\Services\AuthTokenService;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DefinePasswordController extends Controller
{
    public function __invoke(
        DefinePasswordRequest $request,
        InvitationService $invitations,
        AuthTokenService $tokens,
        AuthEventRecorder $events,
    ): Response|JsonResponse {
        // Le mot de passe est validé avant la consommation du lien : un mot de passe refusé ne le brûle pas.
        $user = $invitations->consume((string) $request->validated('token'));

        if ($user === null) {
            $events->record(AuthEventType::InvitationFailed, $request);

            return response()->json(['message' => 'Lien invalide ou expiré.'], 401);
        }

        $user->password = (string) $request->validated('password');

        if ($user->status === UserStatus::Pending) {
            $user->status = UserStatus::Active;
        }

        $user->save();

        $tokens->revokeAllFor($user);

        $events->record(AuthEventType::PasswordDefined, $request, $user);

        return response()->noContent();
    }
}
