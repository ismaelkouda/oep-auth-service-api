<?php

declare(strict_types=1);

use App\Contracts\InvitationSender;
use App\Enums\UserRole;
use App\Models\AuthEvent;
use App\Models\Profile;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Support\Str;
use Tests\Support\RecordingInvitationSender;

beforeEach(function () {
    $this->invitations = new RecordingInvitationSender;
    app()->instance(InvitationSender::class, $this->invitations);

    Profile::factory()->create(['code' => 'citizen']);
    $this->profile = Profile::factory()->create(['code' => 'municipal_police']);

    $this->supervisor = User::factory()->staff(UserRole::Supervisor)->create();
    $this->token = app(AuthTokenService::class)->issue($this->supervisor)->plainText;
});

function staffPayload(object $test, array $overrides = []): array
{
    return array_merge([
        'name' => 'Koffi Yao',
        'email' => 'koffi.yao@oep.test',
        'phone' => '+2250700000123',
        'role' => 'manager',
        'profile_id' => $test->profile->id,
        'organization_id' => (string) Str::uuid(),
        'territory_id' => (string) Str::uuid(),
    ], $overrides);
}

it('refuse les non-superviseurs et les appels sans jeton', function () {
    foreach ([UserRole::TeamLead, UserRole::Manager] as $role) {
        $token = app(AuthTokenService::class)->issue(User::factory()->staff($role)->create())->plainText;

        $this->withToken($token)->getJson('/v1/staff')->assertForbidden();
        $this->withToken($token)->postJson('/v1/staff', staffPayload($this))->assertForbidden();
    }

    $citizen = app(AuthTokenService::class)->issue(User::factory()->create())->plainText;
    $this->withToken($citizen)->getJson('/v1/staff')->assertForbidden();

    auth()->forgetGuards();
    $this->withoutToken()->getJson('/v1/staff')->assertUnauthorized();
});

it('crée un agent en attente et lui envoie une invitation', function () {
    $response = $this->withToken($this->token)->postJson('/v1/staff', staffPayload($this));

    $response->assertCreated()
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('role', 'manager')
        ->assertJsonPath('email', 'koffi.yao@oep.test')
        ->assertJsonMissingPath('password');

    $user = User::where('email', 'koffi.yao@oep.test')->sole();

    expect($user->isStaff())->toBeTrue()
        ->and($user->password)->not->toBeNull()
        ->and($this->invitations->sent)->toHaveCount(1)
        ->and($this->invitations->lastLink())->toStartWith('https://backoffice.test/define-password?token=');

    $invitation = StaffInvitation::where('user_id', $user->id)->sole();

    expect($invitation->token_hash)->toBe(hash('sha256', $this->invitations->lastToken()))
        ->and(StaffInvitation::all()->toJson())->not->toContain($this->invitations->lastToken());

    expect(AuthEvent::where('event', 'staff_created')
        ->where('user_id', $this->supervisor->id)
        ->where('subject_id', $user->id)
        ->count())->toBe(1);
});

it('permet de créer un autre superviseur', function () {
    $this->withToken($this->token)
        ->postJson('/v1/staff', staffPayload($this, ['role' => 'supervisor']))
        ->assertCreated()
        ->assertJsonPath('role', 'supervisor');
});

it('valide les données de création', function () {
    $existing = User::factory()->staff()->create(['phone' => '+2250700000555']);

    $invalid = [
        ['email' => $existing->email],
        ['phone' => '+2250700000555'],
        ['role' => 'administrateur'],
        ['profile_id' => Profile::where('code', 'citizen')->value('id')],
        ['profile_id' => (string) Str::uuid()],
        ['organization_id' => 'pas-un-uuid'],
        ['phone' => '0700000000'],
        ['name' => '1234'],
    ];

    foreach ($invalid as $overrides) {
        $this->withToken($this->token)
            ->postJson('/v1/staff', staffPayload($this, $overrides))
            ->assertUnprocessable();
    }

    $this->withToken($this->token)
        ->postJson('/v1/staff', collect(staffPayload($this))->except('organization_id')->all())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['organization_id']);

    expect($this->invitations->sent)->toBeEmpty();
});

it('réutilise l\'e-mail et le téléphone d\'un agent supprimé', function () {
    $old = User::factory()->staff()->create(['email' => 'koffi.yao@oep.test', 'phone' => '+2250700000123']);
    $old->delete();

    $this->withToken($this->token)->postJson('/v1/staff', staffPayload($this))->assertCreated();
});

it('liste uniquement les agents, avec filtres', function () {
    User::factory()->create();
    User::factory()->staff(UserRole::Manager)->suspended()->create();
    User::factory()->staff(UserRole::TeamLead)->create();

    $this->withToken($this->token)->getJson('/v1/staff')
        ->assertOk()
        ->assertJsonCount(3, 'data');

    $this->withToken($this->token)->getJson('/v1/staff?status=suspended')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->withToken($this->token)->getJson('/v1/staff?role=team_lead')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('affiche un agent et refuse un usager ou un identifiant inconnu', function () {
    $agent = User::factory()->staff()->create();
    $citizen = User::factory()->create();

    $this->withToken($this->token)->getJson('/v1/staff/'.$agent->id)
        ->assertOk()
        ->assertJsonPath('id', $agent->id);

    $this->withToken($this->token)->getJson('/v1/staff/'.$citizen->id)->assertNotFound();
    $this->withToken($this->token)->getJson('/v1/staff/'.Str::uuid())->assertNotFound();
    $this->withToken($this->token)->getJson('/v1/staff/pas-un-uuid')->assertNotFound();
});

it('modifie un agent mais pas son propre rôle', function () {
    $agent = User::factory()->staff(UserRole::Manager)->create(['phone' => '+2250700000777']);

    $this->withToken($this->token)
        ->patchJson('/v1/staff/'.$agent->id, [
            'name' => 'Nouveau Nom',
            'role' => 'team_lead',
            'phone' => '+2250700000777',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Nouveau Nom')
        ->assertJsonPath('role', 'team_lead');

    $this->withToken($this->token)
        ->patchJson('/v1/staff/'.$this->supervisor->id, ['role' => 'manager'])
        ->assertForbidden();

    $this->withToken($this->token)
        ->patchJson('/v1/staff/'.$this->supervisor->id, ['name' => 'Autre Nom'])
        ->assertOk();

    expect(AuthEvent::where('event', 'staff_updated')->count())->toBe(2);
});

it('refuse un téléphone déjà utilisé lors d\'une modification', function () {
    $other = User::factory()->staff()->create();
    $agent = User::factory()->staff()->create();

    $this->withToken($this->token)
        ->patchJson('/v1/staff/'.$agent->id, ['phone' => $other->phone])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone']);
});

it('suspend un agent actif et révoque ses jetons', function () {
    $agent = User::factory()->staff()->create();
    $agentToken = app(AuthTokenService::class)->issue($agent)->plainText;

    $this->withToken($this->token)->postJson('/v1/staff/'.$agent->id.'/suspend')
        ->assertOk()
        ->assertJsonPath('status', 'suspended');

    auth()->forgetGuards();
    $this->withToken($agentToken)->getJson('/v1/auth/me')->assertUnauthorized();

    expect(AuthEvent::where('event', 'staff_suspended')
        ->where('user_id', $this->supervisor->id)
        ->where('subject_id', $agent->id)
        ->count())->toBe(1);
});

it('interdit de suspendre ou supprimer son propre compte', function () {
    $this->withToken($this->token)->postJson('/v1/staff/'.$this->supervisor->id.'/suspend')->assertForbidden();
    $this->withToken($this->token)->deleteJson('/v1/staff/'.$this->supervisor->id)->assertForbidden();

    expect($this->supervisor->refresh()->isActive())->toBeTrue();
});

it('refuse les transitions d\'état impossibles', function () {
    $pending = User::factory()->staff()->pending()->create();
    $active = User::factory()->staff()->create();

    $this->withToken($this->token)->postJson('/v1/staff/'.$pending->id.'/suspend')->assertStatus(409);
    $this->withToken($this->token)->postJson('/v1/staff/'.$active->id.'/reactivate')->assertStatus(409);
});

it('réactive un agent suspendu', function () {
    $agent = User::factory()->staff()->suspended()->create();

    $this->withToken($this->token)->postJson('/v1/staff/'.$agent->id.'/reactivate')
        ->assertOk()
        ->assertJsonPath('status', 'active');
});

it('déverrouille un agent', function () {
    $agent = User::factory()->staff()->locked()->create();

    $this->withToken($this->token)->postJson('/v1/staff/'.$agent->id.'/unlock')
        ->assertOk()
        ->assertJsonPath('locked_until', null);

    expect($agent->refresh()->isLocked())->toBeFalse();
});

it('renvoie une invitation à un agent en attente et refuse un agent suspendu', function () {
    $pending = User::factory()->staff()->pending()->create();
    $suspended = User::factory()->staff()->suspended()->create();

    $this->withToken($this->token)->postJson('/v1/staff/'.$pending->id.'/invite')->assertNoContent();

    expect($this->invitations->sent)->toHaveCount(1);

    $this->withToken($this->token)->postJson('/v1/staff/'.$suspended->id.'/invite')->assertStatus(409);
});

it('supprime un agent (suppression logique) et révoque ses jetons', function () {
    $agent = User::factory()->staff()->create();
    $agentToken = app(AuthTokenService::class)->issue($agent)->plainText;

    $this->withToken($this->token)->deleteJson('/v1/staff/'.$agent->id)->assertNoContent();

    $deleted = User::withTrashed()->findOrFail($agent->id);

    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->status->value)->toBe('deactivated');

    auth()->forgetGuards();
    $this->withToken($agentToken)->getJson('/v1/auth/me')->assertUnauthorized();
});
