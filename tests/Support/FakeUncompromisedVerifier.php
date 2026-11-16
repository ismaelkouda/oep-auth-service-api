<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Contracts\Validation\UncompromisedVerifier;

/**
 * Remplace l'appel réseau à HIBP : seul COMPROMISED est considéré comme compromis.
 */
final class FakeUncompromisedVerifier implements UncompromisedVerifier
{
    public const COMPROMISED = 'compromised-password-1234';

    public function verify($data): bool
    {
        return (string) $data['value'] !== self::COMPROMISED;
    }
}
