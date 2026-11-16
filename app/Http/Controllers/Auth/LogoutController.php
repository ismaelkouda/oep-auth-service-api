<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuthToken;
use App\Services\AuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LogoutController extends Controller
{
    public function __invoke(Request $request, AuthTokenService $tokens): Response
    {
        $token = $request->attributes->get('auth_token');

        if ($token instanceof AuthToken) {
            $tokens->revoke($token);
        }

        return response()->noContent();
    }
}
