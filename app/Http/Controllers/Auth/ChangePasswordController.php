<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuthEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use App\Services\AuthEventRecorder;
use App\Services\AuthTokenService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePasswordController extends Controller
{
    public function __invoke(
        ChangePasswordRequest $request,
        AuthTokenService $tokens,
        AuthEventRecorder $events,
    ): Response {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isStaff()) {
            abort(403, 'Réservé au personnel.');
        }

        if (! Hash::check((string) $request->validated('current_password'), (string) $user->password)) {
            $events->record(AuthEventType::PasswordChangeFailed, $request, $user);

            throw ValidationException::withMessages([
                'current_password' => 'Le mot de passe actuel est incorrect.',
            ]);
        }

        $user->password = (string) $request->validated('password');
        $user->save();

        // Tous les jetons sont révoqués, y compris le courant : l'agent se reconnecte.
        $tokens->revokeAllFor($user);

        $events->record(AuthEventType::PasswordChanged, $request, $user);

        return response()->noContent();
    }
}
