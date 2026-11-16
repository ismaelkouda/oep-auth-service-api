<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\IntrospectRequest;
use App\Services\AuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class IntrospectController extends Controller
{
    public function __invoke(IntrospectRequest $request, AuthTokenService $tokens): JsonResponse
    {
        $token = $tokens->resolve((string) $request->validated('token'));

        if ($token === null) {
            return $this->respond(['active' => false]);
        }

        $user = $token->user;

        $permissions = DB::table('profile_permission')
            ->join('permissions', 'permissions.id', '=', 'profile_permission.permission_id')
            ->where('profile_permission.profile_id', $user->profile_id)
            ->orderBy('permissions.code')
            ->pluck('permissions.code')
            ->all();

        return $this->respond([
            'active' => true,
            'sub' => $user->id,
            'type' => $user->type->value,
            'role' => $user->role?->value,
            'profile_id' => $user->profile_id,
            'permissions' => $permissions,
            'organization_id' => $user->organization_id,
            'territory_id' => $user->territory_id,
            'iat' => $token->created_at->getTimestamp(),
            'exp' => $token->expires_at->getTimestamp(),
            'jti' => $token->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function respond(array $payload): JsonResponse
    {
        return response()->json($payload)->header('Cache-Control', 'no-store');
    }
}
