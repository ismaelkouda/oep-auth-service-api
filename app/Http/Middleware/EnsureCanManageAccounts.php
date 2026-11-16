<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanManageAccounts
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isStaff() || ! $user->isActive() || $user->role !== UserRole::Supervisor) {
            return response()->json(['message' => 'Action réservée aux superviseurs.'], 403);
        }

        return $next($request);
    }
}
