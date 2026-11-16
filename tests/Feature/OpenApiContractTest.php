<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Profile;
use App\Models\User;
use App\Services\AuthTokenService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Osteel\OpenApi\Testing\ValidatorBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

function contractValidator(): object
{
    static $validator = null;

    return $validator ??= ValidatorBuilder::fromYamlFile(base_path('openapi/openapi.yaml'))->getValidator();
}

/**
 * Vérifie que la réponse respecte le contrat. La bibliothèque lève une exception
 * à la moindre différence et renvoie true sinon.
 *
 * @param  TestResponse<Response>  $response
 */
function conforms(TestResponse $response, string $path, string $method): void
{
    expect(contractValidator()->validate($response->baseResponse, $path, $method))->toBeTrue();
}

it('décrit toutes les routes de l\'API, et seulement elles', function () {
    $spec = Yaml::parseFile(base_path('openapi/openapi.yaml'));

    $documented = collect($spec['paths'])
        ->flatMap(fn (array $item, string $path) => collect(array_keys($item))
            ->filter(fn (string $key) => in_array($key, ['get', 'post', 'put', 'patch', 'delete'], true))
            ->map(fn (string $method) => strtoupper($method).' '.$path))
        ->sort()->values()->all();

    $actual = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'v1/'))
        ->flatMap(fn ($route) => collect($route->methods())
            ->reject(fn (string $method) => in_array($method, ['HEAD', 'OPTIONS'], true))
            ->map(fn (string $method) => $method.' '.substr($route->uri(), 2)))
        ->sort()->values()->all();

    expect($actual)->toBe($documented);
});

it('respecte le contrat pour les routes publiques', function () {
    $agent = User::factory()->staff()->create();

    conforms($this->getJson('/v1/ping'), '/ping', 'get');
    conforms($this->getJson('/v1/health/ready'), '/health/ready', 'get');

    conforms($this->postJson('/v1/auth/login', ['email' => $agent->email, 'password' => 'password']), '/auth/login', 'post');
    conforms($this->postJson('/v1/auth/login', ['email' => $agent->email, 'password' => 'faux']), '/auth/login', 'post');
    conforms($this->postJson('/v1/auth/login', []), '/auth/login', 'post');

    conforms($this->postJson('/v1/auth/otp/request', ['phone' => '+2250700000000']), '/auth/otp/request', 'post');
    conforms($this->postJson('/v1/auth/otp/verify', ['phone' => '+2250700000000', 'code' => '000000']), '/auth/otp/verify', 'post');
    conforms($this->postJson('/v1/auth/password/forgot', ['email' => $agent->email]), '/auth/password/forgot', 'post');
    conforms(
        $this->postJson('/v1/auth/password/define', ['token' => 'inconnu', 'password' => 'correct-horse-battery-staple']),
        '/auth/password/define',
        'post',
    );
});

it('respecte le contrat pour une session', function () {
    $agent = User::factory()->staff()->create();
    $token = app(AuthTokenService::class)->issue($agent)->plainText;

    conforms($this->withToken($token)->getJson('/v1/auth/me'), '/auth/me', 'get');
    conforms($this->withToken($token)->postJson('/v1/auth/logout'), '/auth/logout', 'post');

    $this->withoutToken();
    auth()->forgetGuards();
    conforms($this->getJson('/v1/auth/me'), '/auth/me', 'get');
});

it('respecte le contrat pour la gestion des agents', function () {
    $profile = Profile::factory()->create(['code' => 'agent_oep']);
    $supervisor = User::factory()->staff(UserRole::Supervisor)->create(['profile_id' => $profile->id]);
    $target = User::factory()->staff()->create(['profile_id' => $profile->id]);
    $other = User::factory()->staff()->create(['profile_id' => $profile->id]);

    $this->withToken(app(AuthTokenService::class)->issue($supervisor)->plainText);

    conforms($this->getJson('/v1/staff'), '/staff', 'get');

    conforms($this->postJson('/v1/staff', [
        'name' => 'Awa Koné',
        'email' => 'awa.kone@oep.test',
        'phone' => '+2250700000001',
        'role' => 'manager',
        'profile_id' => $profile->id,
        'organization_id' => (string) Str::uuid(),
        'territory_id' => null,
    ]), '/staff', 'post');

    conforms($this->getJson("/v1/staff/{$target->id}"), '/staff/{user}', 'get');
    conforms($this->patchJson("/v1/staff/{$target->id}", ['name' => 'Nouveau Nom']), '/staff/{user}', 'patch');
    conforms($this->postJson("/v1/staff/{$target->id}/suspend"), '/staff/{user}/suspend', 'post');
    conforms($this->postJson("/v1/staff/{$target->id}/suspend"), '/staff/{user}/suspend', 'post');
    conforms($this->postJson("/v1/staff/{$target->id}/reactivate"), '/staff/{user}/reactivate', 'post');
    conforms($this->postJson("/v1/staff/{$target->id}/unlock"), '/staff/{user}/unlock', 'post');
    conforms($this->postJson("/v1/staff/{$target->id}/invite"), '/staff/{user}/invite', 'post');
    conforms($this->getJson('/v1/staff/'.Str::uuid()), '/staff/{user}', 'get');
    conforms($this->postJson('/v1/staff', []), '/staff', 'post');
    conforms($this->deleteJson("/v1/staff/{$other->id}"), '/staff/{user}', 'delete');
});

it('respecte le contrat pour l\'introspection', function () {
    $secret = str_repeat('a', 40);
    config()->set('service_auth.introspection_secrets', [$secret]);

    $agent = User::factory()->staff()->create();
    $token = app(AuthTokenService::class)->issue($agent)->plainText;

    conforms($this->withToken($secret)->postJson('/v1/internal/introspect', ['token' => $token]), '/internal/introspect', 'post');
    conforms($this->withToken($secret)->postJson('/v1/internal/introspect', ['token' => 'oep_inconnu']), '/internal/introspect', 'post');

    $this->withoutToken();
    conforms($this->postJson('/v1/internal/introspect', ['token' => $token]), '/internal/introspect', 'post');
});
