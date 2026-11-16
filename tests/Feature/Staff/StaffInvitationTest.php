<?php

declare(strict_types=1);

use App\Contracts\InvitationSender;
use App\Models\AuthEvent;
use App\Models\AuthToken;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\AuthTokenService;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FakeUncompromisedVerifier;
use Tests\Support\RecordingInvitationSender;

const GOOD_PASSWORD = 'correct-horse-battery-staple';

beforeEach(function () {
    $this->invitations = new RecordingInvitationSender;
    app()->instance(InvitationSender::class, $this->invitations);
});

function inviteAgent(object $test, string $state = 'pending'): array
{
    $factory = User::factory()->staff();

    $user = match ($state) {
        'pending' => $factory->pending()->create(),
        'suspended' => $factory->suspended()->create(),
        default => $factory->create(),
    };

    app(InvitationService::class)->invite($user);

    return [$user, $test->invitations->lastToken()];
}

it('active le compte quand l\'agent définit son mot de passe', function () {
    [$user, $token] = inviteAgent($this);

    $this->postJson('/v1/auth/password/define', ['token' => $token, 'password' => GOOD_PASSWORD])
        ->assertNoContent();

    $user->refresh();

    expect($user->isActive())->toBeTrue()
        ->and(Hash::check(GOOD_PASSWORD, $user->password))->toBeTrue()
        ->and(Hash::info($user->password)['algoName'])->toBe('argon2id')
        ->and(AuthEvent::where('event', 'password_defined')->where('user_id', $user->id)->count())->toBe(1);

    $this->postJson('/v1/auth/login', ['email' => $user->email, 'password' => GOOD_PASSWORD])
        ->assertOk();
});

it('refuse un lien déjà utilisé', function () {
    [$user, $token] = inviteAgent($this);

    $this->postJson('/v1/auth/password/define', ['token' => $token, 'password' => GOOD_PASSWORD])->assertNoContent();

    $this->postJson('/v1/auth/password/define', ['token' => $token, 'password' => 'another-long-password-9'])
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Lien invalide ou expiré.']);

    expect(AuthEvent::where('event', 'invitation_failed')->count())->toBe(1);
});

it('refuse un lien expiré', function () {
    [$user, $token] = inviteAgent($this);

    $this->travel(73)->hours();

    $this->postJson('/v1/auth/password/define', ['token' => $token, 'password' => GOOD_PASSWORD])
        ->assertUnauthorized();
});

it('refuse un lien inconnu ou invalide avec la même réponse', function () {
    foreach (['inconnu', str_repeat('a', 64)] as $token) {
        $this->postJson('/v1/auth/password/define', ['token' => $token, 'password' => GOOD_PASSWORD])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Lien invalide ou expiré.']);
    }
});

it('ne consomme pas le lien quand le mot de passe est refusé', function () {
    [$user, $token] = inviteAgent($this);

    $this->postJson('/v1/auth/password/define', ['token' => $token, 'password' => 'trop-court'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    $this->postJson('/v1/auth/password/define', ['token' => $token, 'password' => FakeUncompromisedVerifier::COMPROMISED])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    $this->postJson('/v1/auth/password/define', ['token' => $token, 'password' => GOOD_PASSWORD])
        ->assertNoContent();
});

it('refuse le lien d\'un agent suspendu', function () {
    [$user, $token] = inviteAgent($this, 'suspended');

    $this->postJson('/v1/auth/password/define', ['token' => $token, 'password' => GOOD_PASSWORD])
        ->assertUnauthorized();
});

it('invalide l\'ancienne invitation quand une nouvelle est envoyée', function () {
    [$user, $oldToken] = inviteAgent($this);

    app(InvitationService::class)->invite($user);
    $newToken = $this->invitations->lastToken();

    $this->postJson('/v1/auth/password/define', ['token' => $oldToken, 'password' => GOOD_PASSWORD])
        ->assertUnauthorized();

    $this->postJson('/v1/auth/password/define', ['token' => $newToken, 'password' => GOOD_PASSWORD])
        ->assertNoContent();
});

it('permet à un agent actif de redéfinir son mot de passe et révoque ses jetons', function () {
    [$user, $token] = inviteAgent($this, 'active');
    app(AuthTokenService::class)->issue($user);

    $this->postJson('/v1/auth/password/define', ['token' => $token, 'password' => GOOD_PASSWORD])
        ->assertNoContent();

    expect($user->refresh()->isActive())->toBeTrue()
        ->and(AuthToken::whereNull('revoked_at')->count())->toBe(0);
});

it('ne laisse aucune invitation si l\'envoi échoue', function () {
    app()->instance(InvitationSender::class, new class implements InvitationSender
    {
        public function send(User $user, string $link): void
        {
            throw new RuntimeException('Envoi impossible');
        }
    });

    $user = User::factory()->staff()->pending()->create();

    expect(fn () => app(InvitationService::class)->invite($user))->toThrow(RuntimeException::class)
        ->and(StaffInvitation::count())->toBe(0);
});

it('purge les invitations expirées depuis plus de 30 jours', function () {
    $user = User::factory()->staff()->pending()->create();

    $old = new StaffInvitation;
    $old->forceFill([
        'user_id' => $user->id,
        'token_hash' => str_repeat('a', 64),
        'expires_at' => now()->subDays(31),
    ])->save();

    $recent = new StaffInvitation;
    $recent->forceFill([
        'user_id' => $user->id,
        'token_hash' => str_repeat('b', 64),
        'expires_at' => now()->subDays(5),
    ])->save();

    $this->artisan('model:prune', ['--model' => [StaffInvitation::class]])->assertSuccessful();

    expect(StaffInvitation::pluck('id')->all())->toBe([$recent->id]);
});
