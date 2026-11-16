<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuthEventType;
use App\Http\Controllers\Controller;
use App\Models\AuthToken;
use App\Services\AuthEventRecorder;
use App\Services\AuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogoutController extends Controller
{
    public function __invoke(Request $request, AuthTokenService $tokens, AuthEventRecorder $events): Response
    {
        $token = $request->attributes->get('auth_token');

        if ($token instanceof AuthToken) {
            $tokens->revoke($token);
            $events->record(AuthEventType::Logout, $request, $token->user);
        }

        return response()->noContent();
    }
}
