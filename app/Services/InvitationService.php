<?php

namespace App\Services;

use App\Contracts\InvitationSender;
use App\Enums\UserStatus;
use App\Models\StaffInvitation;
use App\Models\User;
use RuntimeException;
use Throwable;

final class InvitationService
{
    public function __construct(private readonly InvitationSender $sender) {}

    /**
     * Crée une invitation à usage unique (seule l'empreinte est stockée) et l'envoie.
     * Toute invitation précédente non utilisée est invalidée.
     */
    public function invite(User $user): void
    {
        $link = $this->baseUrl();
        $plainText = bin2hex(random_bytes(32));

        StaffInvitation::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $invitation = new StaffInvitation;
        $invitation->forceFill([
            'user_id' => $user->id,
            'token_hash' => $this->hash($plainText),
            'expires_at' => now()->addHours((int) config('staff.invitation.lifetime_hours')),
        ])->save();

        try {
            $this->sender->send($user, $link.(str_contains($link, '?') ? '&' : '?').'token='.$plainText);
        } catch (Throwable $e) {
            $invitation->delete();

            throw $e;
        }
    }

    /**
     * Consomme une invitation valide et renvoie l'agent concerné, sinon null.
     */
    public function consume(string $plainText): ?User
    {
        $invitation = StaffInvitation::with('user')
            ->where('token_hash', $this->hash($plainText))
            ->first();

        if ($invitation === null || $invitation->used_at !== null || $invitation->expires_at->isPast()) {
            return null;
        }

        $user = $invitation->user;

        if ($user === null || ! $user->isStaff() || ! in_array($user->status, [UserStatus::Pending, UserStatus::Active], true)) {
            return null;
        }

        $consumed = StaffInvitation::whereKey($invitation->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        return $consumed === 1 ? $user : null;
    }

    private function baseUrl(): string
    {
        $url = (string) config('staff.invitation.url');

        if ($url === '') {
            throw new RuntimeException('STAFF_PASSWORD_URL n\'est pas configurée.');
        }

        return $url;
    }

    private function hash(string $plainText): string
    {
        return hash('sha256', $plainText);
    }
}
