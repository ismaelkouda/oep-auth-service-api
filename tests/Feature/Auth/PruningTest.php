<?php

declare(strict_types=1);

use App\Models\AuthEvent;
use App\Models\AuthToken;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\AuthTokenService;

it('purge les jetons expirés ou révoqués depuis plus de 30 jours', function () {
    $service = app(AuthTokenService::class);
    $user = User::factory()->staff()->create();

    $oldExpired = $service->issue($user)->token;
    $oldExpired->forceFill(['expires_at' => now()->subDays(31)])->save();

    $oldRevoked = $service->issue($user)->token;
    $oldRevoked->forceFill(['revoked_at' => now()->subDays(31)])->save();

    $recentExpired = $service->issue($user)->token;
    $recentExpired->forceFill(['expires_at' => now()->subDays(5)])->save();

    $recentRevoked = $service->issue($user)->token;
    $recentRevoked->forceFill(['revoked_at' => now()->subDays(5)])->save();

    $active = $service->issue($user)->token;

    $this->artisan('model:prune', ['--model' => [AuthToken::class]])->assertSuccessful();

    expect(AuthToken::pluck('id')->all())
        ->toEqualCanonicalizing([$recentExpired->id, $recentRevoked->id, $active->id]);
});

it('purge les codes OTP expirés depuis plus de 24 heures', function () {
    $old = new OtpCode;
    $old->forceFill([
        'phone' => '+2250700000001',
        'code_hash' => str_repeat('a', 64),
        'expires_at' => now()->subHours(25),
    ])->save();

    $recent = new OtpCode;
    $recent->forceFill([
        'phone' => '+2250700000001',
        'code_hash' => str_repeat('b', 64),
        'expires_at' => now()->subHour(),
    ])->save();

    $this->artisan('model:prune', ['--model' => [OtpCode::class]])->assertSuccessful();

    expect(OtpCode::pluck('id')->all())->toBe([$recent->id]);
});

it('purge les événements d\'authentification de plus de 365 jours', function () {
    $old = new AuthEvent;
    $old->forceFill(['event' => 'logout', 'created_at' => now()->subDays(400)])->save();

    $recent = new AuthEvent;
    $recent->forceFill(['event' => 'logout', 'created_at' => now()->subDays(10)])->save();

    $this->artisan('model:prune', ['--model' => [AuthEvent::class]])->assertSuccessful();

    expect(AuthEvent::pluck('id')->all())->toBe([$recent->id]);
});
