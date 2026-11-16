<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Règles de gestion des comptes agents.
 *
 * Seul un superviseur actif appelle ces méthodes (middleware) et nul ne peut agir sur son propre
 * compte : il reste donc toujours au moins un superviseur actif (l'auteur de l'action).
 */
final class StaffAccountService
{
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly AuthTokenService $tokens,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = new User;
            $user->forceFill([
                'type' => UserType::Staff,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'role' => $data['role'],
                'profile_id' => $data['profile_id'],
                'organization_id' => $data['organization_id'],
                'territory_id' => $data['territory_id'] ?? null,
                'status' => UserStatus::Pending,
                // La contrainte users_staff_check exige un mot de passe : valeur aléatoire
                // inutilisable, remplacée quand l'agent définit le sien.
                'password' => Str::random(64),
            ])->save();

            $this->invitations->invite($user);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, User $target, array $data): User
    {
        $this->ensureStaff($target);

        if (isset($data['role']) && $actor->is($target) && $data['role'] !== $target->role?->value) {
            throw new AccessDeniedHttpException('Vous ne pouvez pas modifier votre propre rôle.');
        }

        $target->forceFill(Arr::only($data, [
            'name', 'phone', 'role', 'profile_id', 'organization_id', 'territory_id',
        ]))->save();

        return $target->refresh();
    }

    public function suspend(User $actor, User $target): void
    {
        $this->ensureStaff($target);
        $this->ensureNotSelf($actor, $target);

        if ($target->status !== UserStatus::Active) {
            throw new ConflictHttpException('Seul un compte actif peut être suspendu.');
        }

        $target->forceFill(['status' => UserStatus::Suspended])->save();
        $this->tokens->revokeAllFor($target);
    }

    public function reactivate(User $target): void
    {
        $this->ensureStaff($target);

        if ($target->status !== UserStatus::Suspended) {
            throw new ConflictHttpException('Seul un compte suspendu peut être réactivé.');
        }

        $target->forceFill(['status' => UserStatus::Active])->save();
    }

    public function unlock(User $target): void
    {
        $this->ensureStaff($target);

        $target->forceFill(['locked_until' => null])->save();
    }

    public function resendInvitation(User $target): void
    {
        $this->ensureStaff($target);

        if (! in_array($target->status, [UserStatus::Pending, UserStatus::Active], true)) {
            throw new ConflictHttpException('Ce compte ne peut pas recevoir d\'invitation.');
        }

        $this->invitations->invite($target);
    }

    public function delete(User $actor, User $target): void
    {
        $this->ensureStaff($target);
        $this->ensureNotSelf($actor, $target);

        $target->forceFill(['status' => UserStatus::Deactivated])->save();
        $this->tokens->revokeAllFor($target);
        $target->delete();
    }

    public function ensureStaff(User $target): void
    {
        if (! $target->isStaff()) {
            throw new NotFoundHttpException('Compte introuvable.');
        }
    }

    private function ensureNotSelf(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new AccessDeniedHttpException('Action impossible sur votre propre compte.');
        }
    }
}
