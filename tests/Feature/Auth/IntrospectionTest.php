<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Permission;
use App\Models\Profile;
use App\Models\User;
use App\Services\AuthTokenService;

beforeEach(function () {
    $this->secret = str_repeat('a', 40);
    $this->secondSecret = str_repeat('b', 40);

    config()->set('service_auth.introspection_secrets', [$this->secret, $this->secondSecret]);
});

it('refuse un appel sans secret, avec un mauvais secret ou avec un jeton utilisateur', function () {
    $user = User::factory()->staff()->create();
    $userToken = app(AuthTokenService::class)->issue($user)->plainText;

    $this->postJson('/v1/internal/introspect', ['token' => $userToken])
        ->assertUnauthorized();

    $this->withToken(str_repeat('z', 40))->postJson('/v1/internal/introspect', ['token' => $userToken])
        ->assertUnauthorized();

    $this->withToken($userToken)->postJson('/v1/internal/introspect', ['token' => $userToken])
        ->assertUnauthorized();
});

it('refuse tout appel quand aucun secret n\'est configuré', function () {
    config()->set('service_auth.introspection_secrets', []);

    $this->withToken($this->secret)->postJson('/v1/internal/introspect', ['token' => 'oep_x'])
        ->assertUnauthorized();
});

it('accepte chacun des deux secrets pendant une rotation', function () {
    foreach ([$this->secret, $this->secondSecret] as $secret) {
        $this->withToken($secret)->postJson('/v1/internal/introspect', ['token' => 'oep_inconnu'])
            ->assertOk();
    }
});

it('décrit un jeton actif sans exposer de donnée sensible', function () {
    $profile = Profile::factory()->create();
    $other = Profile::factory()->create();

    $view = Permission::create(['code' => 'reports.view']);
    $create = Permission::create(['code' => 'signalements.create']);
    $foreign = Permission::create(['code' => 'admin.everything']);

    $profile->permissions()->attach([$create->id, $view->id]);
    $other->permissions()->attach([$foreign->id]);

    $user = User::factory()->staff(UserRole::Supervisor)->create(['profile_id' => $profile->id]);
    $issued = app(AuthTokenService::class)->issue($user);

    $response = $this->withToken($this->secret)
        ->postJson('/v1/internal/introspect', ['token' => $issued->plainText]);

    $response->assertOk()
        ->assertExactJson([
            'active' => true,
            'sub' => $user->id,
            'type' => 'staff',
            'role' => 'supervisor',
            'profile_id' => $profile->id,
            'permissions' => ['reports.view', 'signalements.create'],
            'organization_id' => $user->organization_id,
            'territory_id' => $user->territory_id,
            'iat' => $issued->token->created_at->getTimestamp(),
            'exp' => $issued->token->expires_at->getTimestamp(),
            'jti' => $issued->token->id,
        ]);

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('répond uniquement {"active": false} pour tout jeton inutilisable', function () {
    $service = app(AuthTokenService::class);

    $revoked = $service->issue(User::factory()->staff()->create());
    $service->revoke($revoked->token);

    $expired = $service->issue(User::factory()->staff()->create());
    $expired->token->forceFill(['expires_at' => now()->subMinute()])->save();

    $suspended = $service->issue(User::factory()->staff()->suspended()->create());

    $deletedUser = User::factory()->staff()->create();
    $deleted = $service->issue($deletedUser);
    $deletedUser->delete();

    $plainTexts = [
        'oep_'.str_repeat('0', 64),
        'pas-un-jeton',
        $revoked->plainText,
        $expired->plainText,
        $suspended->plainText,
        $deleted->plainText,
    ];

    foreach ($plainTexts as $plainText) {
        $this->withToken($this->secret)->postJson('/v1/internal/introspect', ['token' => $plainText])
            ->assertOk()
            ->assertExactJson(['active' => false]);
    }
});

it('exige le champ token', function () {
    $this->withToken($this->secret)->postJson('/v1/internal/introspect', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

it('accepte aussi le format formulaire du RFC 7662', function () {
    $user = User::factory()->staff()->create();
    $issued = app(AuthTokenService::class)->issue($user);

    $this->withToken($this->secret)
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/v1/internal/introspect', ['token' => $issued->plainText])
        ->assertOk()
        ->assertJsonPath('active', true);
});

it('n\'est pas soumis au plafond global de 30 requêtes par minute', function () {
    foreach (range(1, 31) as $_) {
        $this->withToken($this->secret)->postJson('/v1/internal/introspect', ['token' => 'oep_inconnu'])
            ->assertOk();
    }
});
