<?php

namespace App\Services;

use App\Models\AuthToken;
use App\Models\User;

final class AuthTokenService
{
    private const LAST_USED_REFRESH_SECONDS = 60;

    public function issue(User $user): IssuedAuthToken
    {
        $plainText = $this->prefix().bin2hex(random_bytes(32));

        $token = new AuthToken;
        $token->forceFill([
            'user_id' => $user->id,
            'token_hash' => $this->hash($plainText),
            'expires_at' => now()->addMinutes($this->lifetimeMinutes($user)),
        ])->save();

        return new IssuedAuthToken($plainText, $token);
    }

    public function resolve(string $plainText): ?AuthToken
    {
        if (! str_starts_with($plainText, $this->prefix())) {
            return null;
        }

        $token = AuthToken::with('user')
            ->where('token_hash', $this->hash($plainText))
            ->first();

        if ($token === null || ! $token->isActive()) {
            return null;
        }

        if ($token->user === null || ! $token->user->isActive()) {
            return null;
        }

        $this->touch($token);

        return $token;
    }

    public function revoke(AuthToken $token): void
    {
        if ($token->revoked_at === null) {
            $token->forceFill(['revoked_at' => now()])->save();
        }
    }

    public function revokeAllFor(User $user): int
    {
        return AuthToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function touch(AuthToken $token): void
    {
        $lastUsed = $token->last_used_at;

        if ($lastUsed === null || $lastUsed->lt(now()->subSeconds(self::LAST_USED_REFRESH_SECONDS))) {
            $token->forceFill(['last_used_at' => now()])->save();
        }
    }

    private function prefix(): string
    {
        return (string) config('auth_tokens.prefix');
    }

    private function hash(string $plainText): string
    {
        return hash('sha256', $plainText);
    }

    private function lifetimeMinutes(User $user): int
    {
        return (int) config('auth_tokens.lifetime_minutes.'.$user->type->value);
    }
}
