<?php

declare(strict_types=1);

use App\Contracts\OtpSender;
use App\Models\AuthEvent;
use App\Models\OtpCode;
use App\Models\Profile;
use App\Models\User;

const NEW_PHONE = '+2250700000099';

beforeEach(function () {
    $this->sender = new class implements OtpSender
    {
        /** @var array<string, string> */
        public array $codes = [];

        public function send(string $phone, string $code): void
        {
            $this->codes[$phone] = $code;
        }
    };

    app()->instance(OtpSender::class, $this->sender);

    $this->profile = Profile::factory()->create(['code' => 'citizen']);
});

function requestCode(object $test, string $phone): string
{
    $test->postJson('/v1/auth/otp/request', ['phone' => $phone])->assertAccepted();

    return $test->sender->codes[$phone];
}

it('inscrit un nouvel usager avec son nom, son téléphone et son code', function () {
    $code = requestCode($this, NEW_PHONE);

    $response = $this->postJson('/v1/auth/register', [
        'phone' => NEW_PHONE,
        'code' => $code,
        'name' => '  Awa   Koné ',
    ]);

    $response->assertCreated()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.name', 'Awa Koné')
        ->assertJsonPath('user.type', 'citizen')
        ->assertJsonPath('user.status', 'active');

    $user = User::where('phone', NEW_PHONE)->sole();

    expect($user->profile_id)->toBe($this->profile->id)
        ->and($user->phone_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull()
        ->and($user->role)->toBeNull()
        ->and(AuthEvent::where('event', 'registered')->where('user_id', $user->id)->count())->toBe(1);

    $this->withToken($response->json('token'))->getJson('/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

it('accepte les noms avec apostrophe typographique et trait d\'union', function () {
    $code = requestCode($this, NEW_PHONE);

    $this->postJson('/v1/auth/register', [
        'phone' => NEW_PHONE,
        'code' => $code,
        'name' => 'Awa N’Guessan-Koné',
    ])->assertCreated()->assertJsonPath('user.name', 'Awa N’Guessan-Koné');
});

it('refuse un mauvais code sans créer de compte', function () {
    requestCode($this, NEW_PHONE);

    $this->postJson('/v1/auth/register', [
        'phone' => NEW_PHONE,
        'code' => '000000',
        'name' => 'Awa Koné',
    ])->assertUnauthorized()->assertExactJson(['message' => 'Code invalide ou expiré.']);

    expect(User::where('phone', NEW_PHONE)->exists())->toBeFalse();
});

it('ne consomme pas le code quand la validation échoue', function () {
    $code = requestCode($this, NEW_PHONE);

    $this->postJson('/v1/auth/register', [
        'phone' => NEW_PHONE,
        'code' => $code,
    ])->assertUnprocessable()->assertJsonValidationErrors(['name']);

    $this->postJson('/v1/auth/register', [
        'phone' => NEW_PHONE,
        'code' => $code,
        'name' => 'Awa Koné',
    ])->assertCreated();
});

it('refuse un nom invalide', function () {
    $code = requestCode($this, NEW_PHONE);

    foreach (['A', '1234', '<script>', str_repeat('a', 101)] as $name) {
        $this->postJson('/v1/auth/register', [
            'phone' => NEW_PHONE,
            'code' => $code,
            'name' => $name,
        ])->assertUnprocessable()->assertJsonValidationErrors(['name']);
    }

    expect(User::where('phone', NEW_PHONE)->exists())->toBeFalse();
});

it('connecte un citoyen existant sans modifier son nom', function () {
    $citizen = User::factory()->create(['name' => 'Nom Existant']);
    $code = requestCode($this, $citizen->phone);

    $this->postJson('/v1/auth/register', [
        'phone' => $citizen->phone,
        'code' => $code,
        'name' => 'Autre Nom',
    ])->assertOk()->assertJsonPath('user.id', $citizen->id);

    expect($citizen->refresh()->name)->toBe('Nom Existant');
});

it('refuse un numéro d\'agent, même avec un code', function () {
    $staff = User::factory()->staff()->create();

    $this->postJson('/v1/auth/otp/request', ['phone' => $staff->phone])->assertAccepted();

    expect($this->sender->codes)->toBe([]);

    $this->postJson('/v1/auth/register', [
        'phone' => $staff->phone,
        'code' => '123456',
        'name' => 'Intrus',
    ])->assertUnauthorized();
});

it('n\'envoie rien hors des préfixes autorisés et refuse l\'inscription', function () {
    $this->postJson('/v1/auth/otp/request', ['phone' => '+33612345678'])->assertAccepted();

    expect($this->sender->codes)->toBe([]);

    $this->postJson('/v1/auth/register', [
        'phone' => '+33612345678',
        'code' => '123456',
        'name' => 'Awa Koné',
    ])->assertUnauthorized();
});

it('n\'envoie plus rien quand le plafond sur 24 h est atteint', function () {
    config()->set('otp.daily_cap', 2);

    foreach (['+2250700000011', '+2250700000012'] as $phone) {
        $otp = new OtpCode;
        $otp->forceFill([
            'phone' => $phone,
            'code_hash' => str_repeat('a', 64),
            'expires_at' => now()->addMinutes(5),
        ])->save();
    }

    $this->postJson('/v1/auth/otp/request', ['phone' => NEW_PHONE])->assertAccepted();

    expect($this->sender->codes)->toBe([]);
});

it('libère le numéro d\'un compte supprimé pour une nouvelle inscription', function () {
    $old = User::factory()->create(['phone' => NEW_PHONE]);
    $old->delete();

    $code = requestCode($this, NEW_PHONE);

    $response = $this->postJson('/v1/auth/register', [
        'phone' => NEW_PHONE,
        'code' => $code,
        'name' => 'Nouveau Compte',
    ])->assertCreated();

    expect($response->json('user.id'))->not->toBe($old->id);
});
