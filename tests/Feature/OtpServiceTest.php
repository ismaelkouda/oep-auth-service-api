<?php

declare(strict_types=1);

use App\Contracts\OtpSender;
use App\Enums\OtpRequestResult;
use App\Models\OtpCode;
use App\Services\LogOtpSender;
use App\Services\OtpService;

const PHONE = '+2250102030405';

function fakeSender(): object
{
    $sender = new class implements OtpSender
    {
        /** @var list<array{0: string, 1: string}> */
        public array $sent = [];

        public function send(string $phone, string $code): void
        {
            $this->sent[] = [$phone, $code];
        }
    };

    app()->instance(OtpSender::class, $sender);

    return $sender;
}

function otp(): OtpService
{
    return app(OtpService::class);
}

function wrongCode(string $code): string
{
    return $code === '000000' ? '111111' : '000000';
}

it('envoie un code à six chiffres et ne stocke qu\'un hash à clé', function () {
    $sender = fakeSender();

    $result = otp()->request(PHONE);
    $code = $sender->sent[0][1];

    expect($result)->toBe(OtpRequestResult::Sent)
        ->and($sender->sent)->toHaveCount(1)
        ->and($code)->toMatch('/^\d{6}$/')
        ->and(OtpCode::firstOrFail()->code_hash)->toHaveLength(64)->not->toBe($code);
});

it('accepte le bon code une seule fois', function () {
    $sender = fakeSender();
    otp()->request(PHONE);
    $code = $sender->sent[0][1];

    expect(otp()->verify(PHONE, $code))->toBeTrue()
        ->and(otp()->verify(PHONE, $code))->toBeFalse();
});

it('refuse un mauvais code et compte la tentative', function () {
    $sender = fakeSender();
    otp()->request(PHONE);

    expect(otp()->verify(PHONE, wrongCode($sender->sent[0][1])))->toBeFalse()
        ->and(OtpCode::firstOrFail()->attempts)->toBe(1);
});

it('bloque le code après cinq essais, même avec le bon', function () {
    $sender = fakeSender();
    otp()->request(PHONE);
    $code = $sender->sent[0][1];

    foreach (range(1, 5) as $ignored) {
        otp()->verify(PHONE, wrongCode($code));
    }

    expect(otp()->verify(PHONE, $code))->toBeFalse();
});

it('ne compte pas une saisie mal formée', function () {
    fakeSender();
    otp()->request(PHONE);

    expect(otp()->verify(PHONE, 'abc'))->toBeFalse()
        ->and(OtpCode::firstOrFail()->attempts)->toBe(0);
});

it('refuse un code expiré', function () {
    $sender = fakeSender();
    otp()->request(PHONE);

    $this->travel(6)->minutes();

    expect(otp()->verify(PHONE, $sender->sent[0][1]))->toBeFalse();
});

it('invalide l\'ancien code quand un nouveau est envoyé', function () {
    fakeSender();
    otp()->request(PHONE);

    $this->travelTo(now()->addSeconds(61));
    otp()->request(PHONE);

    expect(OtpCode::count())->toBe(2)
        ->and(OtpCode::whereNotNull('consumed_at')->count())->toBe(1);
});

it('impose un délai entre deux envois', function () {
    $sender = fakeSender();
    otp()->request(PHONE);

    expect(otp()->request(PHONE))->toBe(OtpRequestResult::TooSoon)
        ->and($sender->sent)->toHaveCount(1);
});

it('plafonne à trois codes par heure puis reprend', function () {
    fakeSender();

    foreach (range(1, 3) as $ignored) {
        expect(otp()->request(PHONE))->toBe(OtpRequestResult::Sent);
        $this->travelTo(now()->addSeconds(61));
    }

    expect(otp()->request(PHONE))->toBe(OtpRequestResult::LimitReached);

    $this->travelTo(now()->addHour());

    expect(otp()->request(PHONE))->toBe(OtpRequestResult::Sent);
});

it('refuse un numéro qui n\'est pas au format international', function () {
    fakeSender();

    expect(fn () => otp()->request('0102030405'))->toThrow(InvalidArgumentException::class);
});

it('ne laisse aucun code si l\'envoi échoue', function () {
    app()->instance(OtpSender::class, new class implements OtpSender
    {
        public function send(string $phone, string $code): void
        {
            throw new RuntimeException('SMS indisponible');
        }
    });

    expect(fn () => otp()->request(PHONE))->toThrow(RuntimeException::class)
        ->and(OtpCode::count())->toBe(0);
});

it('refuse l\'envoi sans fournisseur configuré', function () {
    config(['otp.driver' => 'none']);

    expect(fn () => app(OtpSender::class))->toThrow(RuntimeException::class);
});

it('utilise l\'envoi par journal hors production', function () {
    config(['otp.driver' => 'log']);

    expect(app(OtpSender::class))->toBeInstanceOf(LogOtpSender::class);
});
