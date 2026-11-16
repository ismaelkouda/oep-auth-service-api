<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuthEventType;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
use App\Services\AuthEventRecorder;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ForgotPasswordController extends Controller
{
    public function __invoke(
        ForgotPasswordRequest $request,
        InvitationService $invitations,
        AuthEventRecorder $events,
    ): JsonResponse {
        $user = User::query()
            ->where('type', UserType::Staff->value)
            ->whereIn('status', [UserStatus::Pending->value, UserStatus::Active->value])
            ->where('email', (string) $request->validated('email'))
            ->first();

        if ($user !== null) {
            // L'envoi se fait après la réponse : le temps de réponse ne révèle pas si le compte existe.
            defer(function () use ($invitations, $user): void {
                try {
                    $invitations->invite($user);
                } catch (Throwable $e) {
                    report($e);
                }
            });
        }

        $events->record(AuthEventType::PasswordResetRequested, $request, $user);

        return response()->json(['message' => 'Si ce compte existe, un lien vient d\'être envoyé.'], 202);
    }
}
