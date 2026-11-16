<?php

declare(strict_types=1);

use App\Contracts\InvitationSender;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Support\RecordingInvitationSender;

beforeEach(function () {
    $this->invitations = new RecordingInvitationSender;
    app()->instance(InvitationSender::class, $this->invitations);

    Profile::factory()->create(['code' => 'citizen']);
    Profile::factory()->create(['code' => 'municipal_police']);
});

it('crée le premier superviseur et envoie son invitation', function () {
    $this->artisan('staff:create', [
        'email' => 'Premier@OEP.test',
        'name' => 'Premier Superviseur',
        'phone' => '+2250700000001',
        'organization' => (string) Str::uuid(),
        'profile' => 'municipal_police',
    ])->assertSuccessful();

    $user = User::where('email', 'premier@oep.test')->sole();

    expect($user->role->value)->toBe('supervisor')
        ->and($user->status->value)->toBe('pending')
        ->and($this->invitations->sent)->toHaveCount(1);
});

it('refuse un profil inconnu ou le profil usager', function () {
    foreach (['inexistant', 'citizen'] as $profile) {
        $this->artisan('staff:create', [
            'email' => 'premier@oep.test',
            'name' => 'Premier Superviseur',
            'phone' => '+2250700000001',
            'organization' => (string) Str::uuid(),
            'profile' => $profile,
        ])->assertFailed();
    }

    expect(User::count())->toBe(0);
});

it('refuse des données invalides', function () {
    $this->artisan('staff:create', [
        'email' => 'pas-un-email',
        'name' => 'Premier Superviseur',
        'phone' => '0700000001',
        'organization' => 'pas-un-uuid',
        'profile' => 'municipal_police',
    ])->assertFailed();

    expect(User::count())->toBe(0);
});
